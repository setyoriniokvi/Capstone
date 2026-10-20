<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Milestone;
use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * F19 — data dashboard mahasiswa: status tim, dokumen per milestone, log bimbingan,
 * tahap timeline, dan daftar "perlu dilakukan" yang diurutkan menurut prioritas.
 *
 * Semua aturan memakai sumber yang sama dengan proses aslinya (ScreeningService untuk prasyarat,
 * Milestone::isOpen()/hasStarted() untuk jendela waktu, status di tabel) — kelas ini tidak
 * menambah aturan bisnis, hanya merangkumnya untuk ditampilkan.
 */
class StudentDashboard
{
    public function __construct(private readonly ScreeningService $screening) {}

    public function for(User $user): array
    {
        $period = Period::active()?->load('milestones', 'registrationRule.courses');
        if (! $period) {
            return ['period' => null];
        }

        $team = $user->teamFor($period)?->load([
            'members.user', 'finalTopic', 'lecturers.lecturer', 'independentTopic',
            'applications' => fn ($q) => $q->where('status', 'menunggu'),
            'documents' => fn ($q) => $q->where('state', 'aktif'),
        ]);
        $eligibility = $this->screening->eligibility($user, $period->registrationRule);
        $uploadBlocker = $team?->documentUploadBlocker();
        $documents = $team ? $this->documents($period, $team, $uploadBlocker !== null) : collect();
        $logbook = $team ? $this->logbookSummary($team) : null;

        return [
            'period' => $period,
            'week' => ['current' => $period->currentWeek(), 'total' => $period->displayWeeks()],
            'team' => $team,
            'supervisor' => $team?->lecturers->firstWhere('role', 'pembimbing')?->lecturer,
            'examiner' => $team?->lecturers->firstWhere('role', 'penguji')?->lecturer,
            'documents' => $documents,
            'documentSummary' => $this->documentSummary($documents),
            'uploadBlocker' => $uploadBlocker,
            'logbook' => $logbook,
            'recentLogbooks' => $team ? $team->logbooks()->reorder()->latest('session_no')->limit(3)->get(['id', 'team_id', 'session_no', 'session_date', 'activity', 'status']) : collect(),
            'actions' => $this->actions($period, $team, $eligibility, $documents, $logbook),
            'waiting' => $this->waiting($team),
            'deadline' => $this->deadline($period, $team),
            'stages' => $this->stages($period, $team),
        ];
    }

    /** Proses yang sedang menunggu pihak lain — bukan tindakan mahasiswa, tetapi perlu diketahui. */
    private function waiting(?Team $team): array
    {
        return array_values(array_filter([
            $team?->status === 'menunggu' ? 'Registrasi tim sedang ditinjau admin.' : null,
            $team?->status === 'diterima' && ! $team->final_topic_id && ($team->independentTopic?->status === 'menunggu' || $team->applications->isNotEmpty())
                ? 'Pengajuan topik tim sedang menunggu keputusan.' : null,
        ]));
    }

    /**
     * Apakah tahap milestone sudah dilalui tim: dokumen dikumpulkan, registrasi diajukan, atau
     * topik final ditetapkan. Milestone lain — dan semua milestone bila belum bertim — dilalui setelah lewat.
     */
    private function settled(Milestone $m, ?Team $team): bool
    {
        if (! $team) {
            return $m->isPast();
        }

        return match ($m->kind) {
            'dokumen' => $team->documents->contains('milestone_id', $m->id),
            'registrasi_tim' => ! in_array($team->status, ['draft', 'ditolak'], true),
            'pengajuan_topik', 'apply_topik_dosen' => (bool) $team->final_topic_id,
            default => $m->isPast(),
        };
    }

    /** Tenggat terdekat yang masih relevan bagi tim (belum lewat dan belum dilalui), beserta tautan halamannya. */
    private function deadline(Period $period, ?Team $team): ?array
    {
        $milestone = $period->milestones
            ->reject(fn (Milestone $m) => $m->isPast() || $this->settled($m, $team))
            ->sortBy('due_at')
            ->first();

        if (! $milestone) {
            return null;
        }

        // Tautan diberi nama tujuannya, bukan "Lihat detail" generik.
        [$route, $label] = match ($milestone->kind) {
            'dokumen' => ['mahasiswa.documents.index', 'Buka dokumen'],
            'registrasi_tim' => ['mahasiswa.team.show', 'Buka Tim Saya'],
            'pengajuan_topik' => ['mahasiswa.topic.index', 'Ajukan topik'],
            'apply_topik_dosen' => ['mahasiswa.bank.index', 'Buka topik dosen'],
            'bimbingan' => ['mahasiswa.logbooks.index', 'Buka log bimbingan'],
            'peer_review' => ['mahasiswa.peer.index', 'Buka peer review'],
            default => [null, null],
        };

        return [
            'milestone' => $milestone,
            'urgency' => $this->urgency($milestone->due_at),
            'url' => $route ? route($route) : null,
            'label' => $label,
        ];
    }

    /**
     * Tahap timeline (config capstone.dashboard_stages) dengan status bagi tim:
     * done (semua milestone dilalui), late (ada yang lewat tenggat tanpa dilalui),
     * current (tahap pertama yang belum selesai), upcoming.
     *
     * @return Collection<int, array{name: string, weeks: array{0: int, 1: int}, state: string, open: bool, milestones: Collection<int, Milestone>}>
     */
    private function stages(Period $period, ?Team $team): Collection
    {
        $reached = false;

        return collect(config('capstone.dashboard_stages'))
            ->map(function (array $keys, string $name) use ($period, $team, &$reached) {
                $milestones = $period->milestones
                    ->filter(fn (Milestone $m) => in_array($m->kind, $keys, true) || in_array("{$m->kind}:{$m->document_type}", $keys, true))
                    ->sortBy('due_at')->values();
                if ($milestones->isEmpty()) {
                    return null;
                }

                $done = $milestones->every(fn (Milestone $m) => $this->settled($m, $team));
                $state = match (true) {
                    $done => 'done',
                    $milestones->contains(fn (Milestone $m) => $m->isPast() && ! $this->settled($m, $team)) => 'late',
                    ! $reached => 'current',
                    default => 'upcoming',
                };
                $reached = $reached || ! $done;
                $weeks = $milestones->map(fn (Milestone $m) => $period->weekOfMilestone($m));

                return [
                    'name' => $name,
                    'weeks' => [$weeks->min(), $weeks->max()],
                    'state' => $state,
                    'open' => $milestones->contains(fn (Milestone $m) => $m->isOpen()),
                    'milestones' => $milestones,
                ];
            })
            ->filter()->values();
    }

    /**
     * Milestone dokumen beserta status kiriman tim. Bila unggah masih diblokir
     * (Team::documentUploadBlocker), milestone yang sudah dibuka berstatus "locked".
     *
     * @return Collection<int, array{milestone: Milestone, document: ?Document, state: string, urgency: ?string}>
     */
    private function documents(Period $period, Team $team, bool $uploadBlocked): Collection
    {
        return $period->milestones->where('kind', 'dokumen')->sortBy('due_at')->values()
            ->map(function (Milestone $m) use ($team, $uploadBlocked) {
                $document = $team->documents->firstWhere('milestone_id', $m->id);

                $state = match (true) {
                    $document?->review_status === 'revisi' => 'revisi',
                    $document !== null => 'done',
                    $uploadBlocked && $m->hasStarted() => 'locked',
                    $m->isPast() => 'missed',
                    $m->hasStarted() => 'open',
                    default => 'upcoming',
                };

                return ['milestone' => $m, 'document' => $document, 'state' => $state, 'urgency' => $state === 'open' ? $this->urgency($m->due_at) : null];
            });
    }

    /**
     * Ringkasan kartu dokumen: jumlah terkumpul dan status terpenting (revisi > missed > open > locked),
     * beserta baris milestone yang menjadi sorotannya.
     *
     * @return array{done: int, total: int, status: string, focus: ?array}
     */
    private function documentSummary(Collection $documents): array
    {
        $focus = collect(['revisi', 'missed', 'open', 'locked'])
            ->map(fn (string $state) => $documents->firstWhere('state', $state))
            ->filter()->first();
        $done = $documents->whereIn('state', ['done', 'revisi'])->count();

        return [
            'done' => $done,
            'total' => $documents->count(),
            'status' => $focus['state'] ?? ($documents->isNotEmpty() && $done === $documents->count() ? 'complete' : 'upcoming'),
            'focus' => $focus ?? $documents->firstWhere('state', 'upcoming'),
        ];
    }

    /** Satu query untuk semua status log bimbingan tim. */
    private function logbookSummary(Team $team): array
    {
        $counts = $team->logbooks()->reorder()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'approved' => (int) ($counts['disetujui'] ?? 0),
            'pending' => (int) ($counts['menunggu'] ?? 0),
            'revision' => (int) ($counts['revisi'] ?? 0),
            'min' => (int) config('capstone.min_approved_logbooks'),
        ];
    }

    /**
     * Tindakan yang dapat dikerjakan mahasiswa sekarang, urut prioritas.
     * Setiap item: title, detail, url, tone (bad|warn|neutral), due (Carbon|null), urgency (late|soon|null).
     */
    private function actions(Period $period, ?Team $team, array $eligibility, Collection $documents, ?array $logbook): Collection
    {
        $actions = collect();
        $failed = collect($eligibility)->where('passed', false);

        if (! $team) {
            if ($failed->isNotEmpty()) {
                // Rincian per syarat ada di halaman Tim Saya; tindakan cukup merangkum.
                $actions->push($this->action(
                    'Prasyarat Capstone belum terpenuhi',
                    $failed->count().' syarat belum terpenuhi — tim belum dapat didaftarkan. Rincian ada di halaman Tim Saya.',
                    route('mahasiswa.team.show'), 'bad',
                ));
            }

            $registration = $period->milestoneOfKind('registrasi_tim');
            if ($failed->isEmpty() && (! $registration || ! $registration->isPast())) {
                $actions->push($this->action(
                    'Bentuk tim Capstone',
                    'Daftarkan tim beserta anggotanya di halaman Tim Saya, atau tunggu didaftarkan oleh ketua tim lain.',
                    route('mahasiswa.team.show'), 'neutral', $registration?->due_at,
                ));
            }

            return $actions;
        }

        if (in_array($team->status, ['draft', 'ditolak'], true)) {
            $actions->push($this->action(
                $team->status === 'ditolak' ? 'Perbaiki dan ajukan ulang registrasi tim' : 'Lengkapi dan ajukan registrasi tim',
                $team->status === 'ditolak' && $team->review_notes ? 'Catatan admin: '.$team->review_notes : 'Lengkapi anggota, daerah asal, dan KHS lalu ajukan registrasi.',
                route('mahasiswa.team.show'), $team->status === 'ditolak' ? 'bad' : 'warn',
                $period->milestoneOfKind('registrasi_tim')?->due_at,
            ));
        }

        if ($team->status === 'diterima' && ! $team->final_topic_id) {
            $waiting = $team->independentTopic?->status === 'menunggu' || $team->applications->isNotEmpty();
            $independent = $period->milestoneOfKind('pengajuan_topik');
            $bank = $period->milestoneOfKind('apply_topik_dosen');

            if (! $waiting && ($independent?->isOpen() || $bank?->isOpen())) {
                $actions->push($this->action(
                    $team->independentTopic?->status === 'ditolak' ? 'Ajukan ulang topik' : 'Ajukan topik Capstone',
                    'Ajukan topik mandiri atau lamar topik dari bank topik dosen.',
                    route($independent?->isOpen() ? 'mahasiswa.topic.index' : 'mahasiswa.bank.index'), 'warn',
                    collect([$independent, $bank])->filter(fn ($m) => $m?->isOpen())->min('due_at'),
                ));
            }
        }

        // Milestone yang unggahnya belum dibuka berstatus "locked", jadi tidak menjadi tindakan.
        foreach ($documents as $row) {
            $m = $row['milestone'];
            if ($row['state'] === 'revisi') {
                $actions->push($this->action('Revisi '.$m->name, $row['document']->feedback ?: 'Dosen meminta revisi dokumen.', route('mahasiswa.documents.index'), 'bad'));
            } elseif (in_array($row['state'], ['open', 'missed'], true)) {
                $actions->push($this->action('Kumpulkan '.$m->name, $row['state'] === 'missed' ? 'Tenggat terlewat — unggahan tetap diterima dan ditandai terlambat.' : '', route('mahasiswa.documents.index'), $row['state'] === 'missed' ? 'bad' : 'warn', $m->due_at));
            }
        }

        if ($logbook && $logbook['revision'] > 0) {
            $actions->push($this->action("Revisi {$logbook['revision']} log bimbingan", 'Dosen pembimbing meminta perbaikan isi log.', route('mahasiswa.logbooks.index'), 'bad'));
        }

        return $actions;
    }

    private function action(string $title, string $detail, string $url, string $tone, ?CarbonInterface $due = null): array
    {
        // Ikon mengikuti menu asal tindakan (sama dengan ikon tombol/menu di halaman tersebut).
        $icon = match ($url) {
            route('mahasiswa.team.show') => 'heroicon-o-user-plus',
            route('mahasiswa.topic.index'), route('mahasiswa.bank.index') => 'heroicon-o-light-bulb',
            route('mahasiswa.documents.index') => 'heroicon-o-document-arrow-up',
            route('mahasiswa.logbooks.index') => 'icons.hand-book',
            default => 'heroicon-o-check-circle',
        };

        return compact('title', 'detail', 'url', 'tone', 'due', 'icon') + ['urgency' => $due ? $this->urgency($due) : null];
    }

    /** "late" bila tenggat lewat, "soon" bila dalam capstone.dashboard_soon_days hari. */
    private function urgency(CarbonInterface $due): ?string
    {
        return match (true) {
            $due->isPast() => 'late',
            $due->lessThanOrEqualTo(now()->addDays(config('capstone.dashboard_soon_days'))) => 'soon',
            default => null,
        };
    }
}
