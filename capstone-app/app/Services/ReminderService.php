<?php

namespace App\Services;

use App\Models\Logbook;
use App\Models\Period;
use App\Models\ReminderDispatch;
use App\Models\ReminderRule;
use App\Models\Team;
use App\Models\TopicApplication;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * F12, F49–F57 — pemantauan tenggat pekerjaan dosen dan antrean notifikasi.
 * Backend menentukan pekerjaan yang belum selesai dan kapan pengingat jatuh tempo;
 * workflow n8n mengambil antrean, mengirim email/WhatsApp, lalu melaporkan hasilnya.
 */
class ReminderService
{
    /**
     * Pekerjaan dosen yang belum selesai pada periode aktif.
     *
     * @return Collection<int, array{type: string, ref: string, lecturer: User, deadline: ?CarbonInterface, items: list<string>, url: string}>
     */
    public function pendingJobs(?Period $period = null, ?int $lecturerId = null): Collection
    {
        $period ??= Period::active();
        if (! $period) {
            return collect();
        }

        return $this->assessmentJobs($period, $lecturerId)
            ->concat($this->logbookJobs($period, $lecturerId))
            ->concat($this->applicantJobs($period, $lecturerId))
            ->values();
    }

    /** F12 — komponen nilai yang belum diisi per dosen, tanpa memandang jendela waktu. */
    public function incompleteAssessments(Period $period, ?int $lecturerId = null): Collection
    {
        $rows = collect();
        $components = collect(config('capstone.components'))->reject(fn ($c) => $c['role'] === 'mahasiswa');

        $teams = Team::query()->where('period_id', $period->id)->where('status', 'diterima')
            ->with(['lecturers.lecturer', 'assessments' => fn ($q) => $q->where('status', 'final')])
            ->get();

        foreach ($teams as $team) {
            foreach ($components as $component => $meta) {
                $assignment = $team->lecturers->firstWhere('role', $meta['role']);
                if (! $assignment || ($lecturerId && $assignment->lecturer_id !== $lecturerId)) {
                    continue;
                }
                $done = $team->assessments->contains(fn ($a) => $a->component === $component && $a->assessor_id === $assignment->lecturer_id);
                $rows->push([
                    'lecturer' => $assignment->lecturer,
                    'team' => $team,
                    'component' => $component,
                    'role' => $meta['role'],
                    'done' => $done,
                ]);
            }
        }

        return $rows;
    }

    private function assessmentJobs(Period $period, ?int $lecturerId): Collection
    {
        $windows = $period->assessmentWindows()->get()->keyBy('component');

        return $this->incompleteAssessments($period, $lecturerId)
            ->where('done', false)
            ->filter(fn ($row) => $windows->has($row['component']))
            ->groupBy(fn ($row) => $row['component'].':'.$row['lecturer']->id)
            ->map(function (Collection $rows) use ($period, $windows) {
                $first = $rows->first();
                $label = config("capstone.components.{$first['component']}.label");

                return [
                    'type' => 'penilaian',
                    'ref' => "penilaian:{$period->id}:{$first['component']}:{$first['lecturer']->id}",
                    'lecturer' => $first['lecturer'],
                    'title' => "Pengisian nilai {$label}",
                    'deadline' => $windows[$first['component']]->due_at,
                    'items' => $rows->map(fn ($r) => "{$r['team']->code} · {$r['team']->name}")->values()->all(),
                    'url' => route('dosen.assessments.index', ['component' => $first['component']]),
                ];
            })->values();
    }

    private function logbookJobs(Period $period, ?int $lecturerId): Collection
    {
        $milestone = $period->milestones()->where('kind', 'bimbingan')->orderByDesc('due_at')->first();
        if (! $milestone) {
            return collect();
        }

        return Logbook::query()->where('status', 'menunggu')
            ->whereHas('team', fn ($q) => $q->where('period_id', $period->id))
            ->with('team.supervisor.lecturer')
            ->get()
            ->filter(fn (Logbook $l) => $l->team->supervisor && (! $lecturerId || $l->team->supervisor->lecturer_id === $lecturerId))
            ->groupBy(fn (Logbook $l) => $l->team->supervisor->lecturer_id)
            ->map(function (Collection $logs) use ($milestone) {
                $lecturer = $logs->first()->team->supervisor->lecturer;

                return [
                    'type' => 'approval_logbook',
                    'ref' => "approval_logbook:{$milestone->id}:{$lecturer->id}",
                    'lecturer' => $lecturer,
                    'title' => 'Approval log bimbingan',
                    'deadline' => $milestone->due_at,
                    'items' => $logs->groupBy('team_id')->map(fn ($g) => "{$g->first()->team->name} · {$g->count()} log menunggu")->values()->all(),
                    'url' => route('dosen.logbooks.index'),
                ];
            })->values();
    }

    private function applicantJobs(Period $period, ?int $lecturerId): Collection
    {
        $milestone = $period->milestones()->where('kind', 'apply_topik_dosen')->orderByDesc('due_at')->first();
        if (! $milestone) {
            return collect();
        }

        return TopicApplication::query()->where('status', 'menunggu')
            ->whereHas('topic', fn ($q) => $q->where('period_id', $period->id)->when($lecturerId, fn ($q) => $q->where('lecturer_id', $lecturerId)))
            ->with('topic.lecturer', 'team')
            ->get()
            ->groupBy(fn ($a) => $a->topic->lecturer_id)
            ->map(function (Collection $apps) use ($milestone) {
                $lecturer = $apps->first()->topic->lecturer;

                return [
                    'type' => 'approval_pelamar',
                    'ref' => "approval_pelamar:{$milestone->id}:{$lecturer->id}",
                    'lecturer' => $lecturer,
                    'title' => 'Approval tim pelamar topik',
                    'deadline' => $milestone->due_at,
                    'items' => $apps->map(fn ($a) => "{$a->team->name} → {$a->topic->title}")->values()->all(),
                    'url' => route('dosen.applications.index'),
                ];
            })->values();
    }

    /** F50 / F54 — antrekan pengingat yang sudah mencapai ambang waktu, tanpa duplikat. */
    public function queueDue(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $created = 0;
        $rules = ReminderRule::query()->where('is_active', true)->get();
        if ($rules->isEmpty()) {
            return 0;
        }

        $jobs = $this->pendingJobs();

        foreach ($rules as $rule) {
            foreach ($jobs->where('type', $rule->job_type) as $job) {
                $deadline = $job['deadline'];
                if (! $deadline || $now->greaterThanOrEqualTo($deadline) || $now->lessThan($deadline->copy()->subHours($rule->hours_before))) {
                    continue;
                }

                foreach ($rule->channels as $channel) {
                    $dispatch = ReminderDispatch::query()->firstOrCreate(
                        ['dedupe_key' => "{$job['ref']}|rule:{$rule->id}|{$channel}"],
                        $this->dispatchAttributes($job, $channel, 'otomatis', $rule->id)
                    );
                    $created += (int) $dispatch->wasRecentlyCreated;
                }
            }
        }

        return $created;
    }

    /** F12 — reminder manual per dosen atau massal. */
    public function queueManual(Collection $lecturers, array $channels, User $admin): int
    {
        $period = Period::active();
        $count = 0;

        foreach ($lecturers as $lecturer) {
            $job = $this->pendingJobs($period, $lecturer->id)->where('type', 'penilaian');
            if ($job->isEmpty()) {
                // Tidak ada jendela penilaian aktif: kirim ringkasan komponen yang belum diisi.
                $rows = $this->incompleteAssessments($period, $lecturer->id)->where('done', false);
                if ($rows->isEmpty()) {
                    continue;
                }
                $job = collect([[
                    'type' => 'penilaian',
                    'ref' => "manual:{$period->id}:{$lecturer->id}",
                    'lecturer' => $lecturer,
                    'title' => 'Pengisian nilai',
                    'deadline' => null,
                    'items' => $rows->map(fn ($r) => "{$r['team']->code} · ".config("capstone.components.{$r['component']}.label"))->values()->all(),
                    'url' => route('dosen.assessments.index'),
                ]]);
            }

            foreach ($job as $j) {
                foreach ($channels as $channel) {
                    ReminderDispatch::create(
                        ['dedupe_key' => "manual|{$j['ref']}|{$channel}|".now()->format('YmdHis').'|'.$lecturer->id]
                        + $this->dispatchAttributes($j, $channel, 'manual', null, $admin)
                    );
                    $count++;
                }
            }
        }

        $this->pingWorkflow();

        return $count;
    }

    /**
     * F53 / F57 — n8n mengambil antrean. Pekerjaan diperiksa ulang sebelum dikirim;
     * bila sudah selesai, pengingat dibatalkan.
     */
    public function claim(int $limit = 50): Collection
    {
        $this->queueDue();
        $max = (int) config('capstone.automation.max_attempts', 3);

        ReminderDispatch::query()->where('status', 'diproses')
            ->where('claimed_at', '<', now()->subMinutes(30))
            ->update(['status' => 'antre']);

        $candidates = ReminderDispatch::query()
            ->where('recipient', '!=', '')
            ->where(fn ($q) => $q->where('status', 'antre')
                ->orWhere(fn ($q) => $q->where('status', 'gagal')->where('attempts', '<', $max)))
            ->with('lecturer')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $jobsByLecturer = [];
        $claimed = collect();

        foreach ($candidates as $dispatch) {
            $jobs = $jobsByLecturer[$dispatch->lecturer_id] ??= $this->pendingJobs(null, $dispatch->lecturer_id);
            $job = $dispatch->trigger === 'manual' && str_starts_with($dispatch->job_ref, 'manual:')
                ? $this->manualJob($dispatch)
                : $jobs->firstWhere('ref', $dispatch->job_ref);

            if (! $job || $job['items'] === []) {
                $dispatch->update(['status' => 'dibatalkan', 'last_error' => 'Pekerjaan sudah diselesaikan sebelum pengingat dikirim.']);

                continue;
            }

            $updated = DB::table('reminder_dispatches')->where('id', $dispatch->id)
                ->whereIn('status', ['antre', 'gagal'])
                ->update(['status' => 'diproses', 'claimed_at' => now(), 'attempts' => $dispatch->attempts + 1, 'updated_at' => now()]);

            if ($updated === 0) {
                continue;
            }

            $payload = $this->payload($job);
            $dispatch->update(['payload' => $payload]);
            $claimed->push(['id' => $dispatch->id, 'channel' => $dispatch->channel, 'recipient' => $dispatch->recipient] + $payload);
        }

        return $claimed;
    }

    /** F55 — hasil pengiriman dari n8n. */
    public function report(ReminderDispatch $dispatch, bool $success, ?string $error = null): void
    {
        $dispatch->update($success
            ? ['status' => 'terkirim', 'sent_at' => now(), 'last_error' => null]
            : ['status' => 'gagal', 'last_error' => mb_substr((string) $error, 0, 1000)]);
    }

    private function manualJob(ReminderDispatch $dispatch): ?array
    {
        $period = Period::active();
        $rows = $period ? $this->incompleteAssessments($period, $dispatch->lecturer_id)->where('done', false) : collect();
        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'type' => 'penilaian',
            'ref' => $dispatch->job_ref,
            'lecturer' => $dispatch->lecturer,
            'title' => 'Pengisian nilai',
            'deadline' => null,
            'items' => $rows->map(fn ($r) => "{$r['team']->code} · ".config("capstone.components.{$r['component']}.label"))->values()->all(),
            'url' => route('dosen.assessments.index'),
        ];
    }

    private function dispatchAttributes(array $job, string $channel, string $trigger, ?int $ruleId, ?User $admin = null): array
    {
        $recipient = $channel === 'email' ? (string) $job['lecturer']->email : $this->normalizePhone($job['lecturer']->phone);

        return [
            'job_type' => $job['type'],
            'job_ref' => $job['ref'],
            'lecturer_id' => $job['lecturer']->id,
            'reminder_rule_id' => $ruleId,
            'trigger' => $trigger,
            'channel' => $channel,
            'recipient' => $recipient,
            'payload' => $this->payload($job),
            'deadline_at' => $job['deadline'],
            'status' => $recipient === '' ? 'gagal' : 'antre',
            'last_error' => $recipient === '' ? ($channel === 'email' ? 'Email dosen kosong.' : 'Nomor WhatsApp dosen kosong.') : null,
            'created_by' => $admin?->id,
        ];
    }

    /** F52 — isi pesan pengingat. */
    public function payload(array $job): array
    {
        $deadline = $job['deadline'];
        $remaining = $deadline ? $this->remaining($deadline) : null;
        $lines = collect($job['items'])->map(fn ($i) => "• {$i}")->implode("\n");
        $deadlineText = $deadline ? $deadline->timezone(config('app.timezone'))->translatedFormat('l, j F Y H:i').' WIB' : null;

        $message = "Yth. {$job['lecturer']->name},\n\n"
            ."Pengingat Capstone: {$job['title']} belum diselesaikan untuk:\n{$lines}\n\n"
            .($deadlineText ? "Batas waktu: {$deadlineText}\nSisa waktu: {$remaining}\n\n" : '')
            ."Buka halaman pekerjaan: {$job['url']}\n\n"
            .'Pesan otomatis dari Capstone FRI Telkom University.';

        return [
            'lecturer_name' => $job['lecturer']->name,
            'job_type' => $job['type'],
            'job_title' => $job['title'],
            'items' => $job['items'],
            'deadline' => $deadline?->toIso8601String(),
            'deadline_text' => $deadlineText,
            'remaining_text' => $remaining,
            'url' => $job['url'],
            'subject' => "[Capstone] {$job['title']}".($remaining ? " — sisa {$remaining}" : ''),
            'message' => $message,
        ];
    }

    private function remaining(CarbonInterface $deadline): string
    {
        $minutes = max(0, (int) now()->diffInMinutes($deadline, false));
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        return match (true) {
            $minutes === 0 => 'tenggat sudah lewat',
            $days > 0 => "{$days} hari {$hours} jam",
            $hours > 0 => "{$hours} jam",
            default => "{$minutes} menit",
        };
    }

    public function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return match (true) {
            $digits === '' => '',
            str_starts_with($digits, '0') => '62'.substr($digits, 1),
            str_starts_with($digits, '8') => '62'.$digits,
            default => $digits,
        };
    }

    /** Memicu workflow n8n segera (opsional). Gagal memicu tidak membatalkan antrean. */
    private function pingWorkflow(): void
    {
        $url = config('capstone.automation.webhook_url');
        if (! $url) {
            return;
        }

        try {
            Http::timeout(5)->withHeaders(['X-Automation-Token' => (string) config('capstone.automation.token')])->post($url, ['source' => 'manual']);
        } catch (Throwable) {
            // Antrean tetap diambil pada jadwal pemeriksaan berikutnya.
        }
    }
}
