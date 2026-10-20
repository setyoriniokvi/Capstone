<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ForbiddenKeyword;
use App\Models\Team;
use App\Models\TeamLecturer;
use App\Models\Topic;
use App\Models\TopicApplication;
use App\Models\User;
use App\Notifications\SystemNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F7, F8, F22, F23, F42, F43 — alur topik mandiri & topik dosen.
 */
class TopicService
{
    /** F7 — cek kata kunci yang tidak diperbolehkan. */
    public function keywordHits(string $text): array
    {
        $haystack = mb_strtolower($text);

        return ForbiddenKeyword::query()->pluck('keyword')
            ->filter(fn (string $k) => preg_match('/(?<![\p{L}\p{N}])'.preg_quote(mb_strtolower($k), '/').'(?![\p{L}\p{N}])/u', $haystack) === 1)
            ->values()
            ->all();
    }

    /** F23 — buat / ubah pengajuan topik mandiri. */
    public function saveIndependent(Team $team, array $data, User $actor): Topic
    {
        if ($team->status !== 'diterima') {
            throw ValidationException::withMessages(['title' => 'Topik mandiri dapat diajukan setelah registrasi tim diterima.']);
        }

        $topic = $team->independentTopic;
        if ($topic && $topic->status === 'diterima') {
            throw ValidationException::withMessages(['title' => 'Topik mandiri yang sudah diterima tidak dapat diubah.']);
        }

        $payload = [
            'period_id' => $team->period_id,
            'source' => 'mandiri',
            'team_id' => $team->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'field' => $data['field'] ?? $topic?->field,
            'object' => $data['object'] ?? null,
            'status' => 'menunggu',
            'review_notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'keyword_hits' => $this->keywordHits($data['title'].' '.$data['description']),
        ];

        $topic = $topic ? tap($topic)->update($payload) : Topic::create($payload);
        ActivityLog::record('submit_topic', $topic);

        return $topic;
    }

    /** F42 — dosen menambah / mengubah topik; perubahan kembali menunggu approval admin. */
    public function saveLecturerTopic(?Topic $topic, array $data, User $lecturer, int $periodId): Topic
    {
        if ($topic && $topic->applications()->where('status', 'diterima')->exists() && (int) $data['quota'] < $topic->acceptedCount()) {
            throw ValidationException::withMessages(['quota' => 'Kuota tidak boleh lebih kecil dari jumlah tim yang sudah diterima.']);
        }

        $payload = [
            'period_id' => $periodId,
            'source' => 'dosen',
            'lecturer_id' => $lecturer->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'field' => $data['field'] ?? $topic?->field,
            'object' => $data['object'],
            'competencies' => $data['competencies'],
            'prodi_code' => $data['prodi_code'],
            'quota' => (int) $data['quota'],
            'keyword_hits' => $this->keywordHits($data['title'].' '.$data['description']),
        ];

        // Perubahan isi topik wajib disetujui ulang; perubahan kuota saja tidak.
        $contentChanged = ! $topic
            || $topic->title !== $payload['title']
            || $topic->description !== $payload['description']
            || $topic->field !== $payload['field']
            || $topic->object !== $payload['object']
            || $topic->competencies !== $payload['competencies']
            || $topic->prodi_code !== $payload['prodi_code'];

        if ($topic?->status !== 'diterima' || $contentChanged) {
            $payload += ['status' => 'menunggu', 'review_notes' => null, 'reviewed_by' => null, 'reviewed_at' => null];
        }

        return $topic ? tap($topic)->update($payload) : Topic::create($payload);
    }

    /** F7 — admin menyetujui / menolak topik. */
    public function review(Topic $topic, bool $approve, ?string $notes, User $admin): void
    {
        DB::transaction(function () use ($topic, $approve, $notes, $admin) {
            $topic->update([
                'status' => $approve ? 'diterima' : 'ditolak',
                'review_notes' => $notes,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if ($topic->source === 'mandiri') {
                $this->refreshFinalTopic($topic->team);
            }
        });

        ActivityLog::record($approve ? 'approve_topic' : 'reject_topic', $topic, ['notes' => $notes]);

        $title = $approve ? 'Topik diterima' : 'Topik ditolak';
        $recipients = $topic->source === 'mandiri' ? $topic->team->activeUsers : collect([$topic->lecturer]);
        $url = $topic->source === 'mandiri' ? route('mahasiswa.topic.index') : route('dosen.topics.index');
        SystemNotice::sendTo($recipients, $title, "“{$topic->title}”".($notes ? " — {$notes}" : ''), $url);
    }

    /** F22 — tim melamar topik dosen. */
    public function apply(Team $team, Topic $topic, string $portfolioUrl): TopicApplication
    {
        $independent = $team->independentTopic;

        $errors = match (true) {
            $team->status !== 'diterima' => 'Registrasi tim belum diterima.',
            ! $independent || $independent->status !== 'diterima' => 'Topik mandiri tim harus diterima terlebih dahulu.',
            $topic->source !== 'dosen' || $topic->status !== 'diterima' || $topic->period_id !== $team->period_id => 'Topik tidak tersedia.',
            $team->applications()->where('status', 'diterima')->exists() => 'Tim sudah diterima di topik dosen.',
            $team->applications()->where('topic_id', $topic->id)->whereIn('status', ['menunggu', 'ditolak'])->exists() => 'Tim sudah pernah mengajukan topik ini.',
            $topic->remainingQuota() === 0 => 'Kuota topik ini sudah penuh. Pilih topik lain.',
            default => null,
        };

        if ($errors) {
            throw ValidationException::withMessages(['topic' => $errors]);
        }

        $application = TopicApplication::create([
            'topic_id' => $topic->id,
            'team_id' => $team->id,
            'portfolio_url' => $portfolioUrl,
        ]);

        SystemNotice::sendTo([$topic->lecturer], 'Lamaran topik baru', "{$team->name} melamar “{$topic->title}”.", route('dosen.applications.index'));

        return $application;
    }

    public function withdraw(TopicApplication $application): void
    {
        if ($application->status !== 'menunggu') {
            throw ValidationException::withMessages(['topic' => 'Hanya lamaran yang menunggu yang dapat dibatalkan.']);
        }
        $application->update(['status' => 'dibatalkan', 'decided_at' => now()]);
    }

    /** F43 — dosen menerima / menolak tim pelamar. Diterima → dosen otomatis menjadi pembimbing (PRD 6.4). */
    public function decide(TopicApplication $application, bool $accept, ?string $notes, User $lecturer): void
    {
        DB::transaction(function () use ($application, $accept, $notes, $lecturer) {
            $application = TopicApplication::query()->lockForUpdate()->findOrFail($application->id);
            $topic = Topic::query()->lockForUpdate()->findOrFail($application->topic_id);

            if ($application->status !== 'menunggu') {
                throw ValidationException::withMessages(['application' => 'Lamaran ini sudah diputuskan.']);
            }

            if ($accept && $topic->remainingQuota() === 0) {
                throw ValidationException::withMessages(['application' => 'Kuota topik ini sudah penuh.']);
            }

            $application->update([
                'status' => $accept ? 'diterima' : 'ditolak',
                'notes' => $notes,
                'decided_by' => $lecturer->id,
                'decided_at' => now(),
            ]);

            if (! $accept) {
                return;
            }

            $team = $application->team;
            // Satu topik dosen diterima → pengajuan tim lain yang masih menunggu otomatis dibatalkan.
            $team->applications()->whereKeyNot($application->id)->where('status', 'menunggu')->update([
                'status' => 'dibatalkan',
                'notes' => 'Dibatalkan otomatis: tim sudah diterima di topik lain.',
                'decided_at' => now(),
            ]);
            TeamLecturer::updateOrCreate(
                ['team_id' => $team->id, 'role' => 'pembimbing'],
                ['lecturer_id' => $topic->lecturer_id, 'source' => 'sistem', 'assigned_by' => null]
            );
            $this->refreshFinalTopic($team);

            if ($topic->remainingQuota() === 0) {
                $topic->applications()->where('status', 'menunggu')->update([
                    'status' => 'ditolak',
                    'notes' => 'Kuota topik sudah penuh.',
                    'decided_at' => now(),
                ]);
            }
        });

        $application->refresh();
        ActivityLog::record($accept ? 'accept_application' : 'reject_application', $application, ['notes' => $notes]);
        SystemNotice::sendTo(
            $application->team->activeUsers,
            $accept ? 'Lamaran topik diterima' : 'Lamaran topik ditolak',
            "“{$application->topic->title}”".($notes ? " — {$notes}" : ''),
            route('mahasiswa.topic.index')
        );
    }

    /** F8 — topik final: topik dosen yang diterima menggantikan topik mandiri. */
    public function refreshFinalTopic(Team $team): void
    {
        $accepted = $team->applications()->where('status', 'diterima')->latest('decided_at')->first();
        $independent = $team->topics()->where('source', 'mandiri')->where('status', 'diterima')->latest('id')->first();

        $team->update(['final_topic_id' => $accepted?->topic_id ?? $independent?->id]);
    }
}
