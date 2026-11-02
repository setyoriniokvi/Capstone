<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ForbiddenKeyword;
use App\Models\LecturerProfile;
use App\Models\RuleSpecialization;
use App\Models\Team;
use App\Models\TeamLecturer;
use App\Models\Topic;
use App\Models\TopicApplication;
use App\Models\User;
use App\Notifications\SystemNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * F7, F8, F22, F23, F42, F43 — alur topik mandiri & topik dosen.
 */
class TopicService
{
    /** F7 — cek kata kunci yang tidak diperbolehkan untuk jenis topik tersebut (mandiri / dosen). */
    public function keywordHits(string $text, string $source): array
    {
        $haystack = mb_strtolower($text);

        return ForbiddenKeyword::query()->where('source', $source)->pluck('keyword')
            ->filter(fn (string $k) => preg_match('/(?<![\p{L}\p{N}])'.preg_quote(mb_strtolower($k), '/').'(?![\p{L}\p{N}])/u', $haystack) === 1)
            ->values()
            ->all();
    }

    /** Periksa ulang topik yang masih menunggu approval setelah daftar kata kunci terlarang jenis topik itu berubah. */
    public function refreshKeywordHits(string $source): int
    {
        $topics = Topic::query()->where('source', $source)->where('status', 'menunggu')->get();
        foreach ($topics as $topic) {
            $topic->update(['keyword_hits' => $this->keywordHits($topic->title.' '.$topic->description, $source)]);
        }

        return $topics->count();
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
        // Topik dosen yang diterima sudah menjadi topik final (F8), jadi topik mandiri tidak diperlukan lagi.
        if ($team->applications()->where('status', 'diterima')->exists()) {
            throw ValidationException::withMessages(['title' => 'Tim sudah diterima di topik dosen; topik mandiri tidak dapat diajukan lagi.']);
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
            'keyword_hits' => $this->keywordHits($data['title'].' '.$data['description'], 'mandiri'),
        ];

        $topic = $topic ? tap($topic)->update($payload) : Topic::create($payload);
        ActivityLog::record('submit_topic', $topic);

        return $topic;
    }

    /**
     * Aturan validasi formulir topik dosen (dosen maupun admin). Peminatan = peminatan pada aturan registrasi periode.
     *
     * @return array{0: array, 1: array} [rules, messages]
     */
    public function lecturerTopicRules(int $periodId): array
    {
        $allowed = RuleSpecialization::query()->whereHas('rule', fn ($q) => $q->where('period_id', $periodId))->pluck('name')->all();
        $lecturer = Rule::exists('users', 'id')->where('role', 'dosen')->where('is_active', true);

        return [[
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'competencies' => ['required', 'string', 'max:5000'],
            'prodi_codes' => ['required', 'array', 'min:1'],
            'prodi_codes.*' => ['string', 'distinct', 'exists:study_programs,code'],
            'specializations' => ['required', 'array', 'min:1'],
            'specializations.*' => ['string', 'distinct', Rule::in($allowed)],
            'object' => ['required', 'string', 'max:255'],
            'quota' => ['required', 'integer', 'between:1,20'],
            'proposer_ids' => ['array'],
            'proposer_ids.*' => ['integer', 'distinct', $lecturer],
        ], [
            'competencies.required' => 'Isi kompetensi yang diharapkan.',
            'prodi_codes.required' => 'Pilih minimal satu program studi.',
            'specializations.required' => 'Pilih minimal satu peminatan.',
            'specializations.*.in' => 'Pilih peminatan yang tersedia pada periode ini.',
            'object.required' => 'Isi instansi, organisasi, atau perusahaan objek.',
            'proposer_ids.*.exists' => 'Dosen pengusul tidak ditemukan.',
            'lecturer_id.required' => 'Pilih dosen pengusul utama.',
        ]];
    }

    /**
     * F42 — dosen menambah / mengubah topik; perubahan isi kembali menunggu approval admin. Pembuat topik menjadi
     * pengusul utama; dosen yang mengisi formulir dan dosen di $data['proposer_ids'] menjadi pengusul bersama
     * (akses penuh), dan dosen yang baru diundang diberi notifikasi.
     */
    public function saveLecturerTopic(?Topic $topic, array $data, User $lecturer, int $periodId): Topic
    {
        $payload = $this->lecturerTopicPayload($topic, $data, $topic?->lecturer_id ?? $lecturer->id, $periodId);

        // Perubahan isi topik wajib disetujui ulang; perubahan kuota atau dosen pengusul saja tidak.
        $contentChanged = ! $topic
            || $topic->title !== $payload['title']
            || $topic->description !== $payload['description']
            || $topic->competencies !== $payload['competencies']
            || collect($topic->specializations)->sort()->values()->all() !== collect($payload['specializations'])->sort()->values()->all()
            || $topic->field !== $payload['field']
            || $topic->object !== $payload['object']
            || collect($topic->prodi_codes)->sort()->values()->all() !== collect($payload['prodi_codes'])->sort()->values()->all();

        if ($topic?->status !== 'diterima' || $contentChanged) {
            $payload += ['status' => 'menunggu', 'review_notes' => null, 'reviewed_by' => null, 'reviewed_at' => null];
        }

        return DB::transaction(function () use ($topic, $payload, $data, $lecturer) {
            $topic = $topic ? tap($topic)->update($payload) : Topic::create($payload);
            $this->syncProposers($topic, $data['proposer_ids'] ?? [], $lecturer);

            return $topic;
        });
    }

    /**
     * Admin menambah / mengubah topik di bank topik dosen: langsung terbit (tanpa approval ulang). Pengusul utama dipilih
     * admin ($data['lecturer_id']); seluruh pengusul topik baru diberi tahu lewat notifikasi.
     */
    public function saveByAdmin(?Topic $topic, array $data, User $admin, int $periodId): Topic
    {
        $payload = $this->lecturerTopicPayload($topic, $data, (int) $data['lecturer_id'], $periodId);
        if (! $topic || $topic->status !== 'diterima') {
            $payload += ['status' => 'diterima', 'review_notes' => null, 'reviewed_by' => $admin->id, 'reviewed_at' => now()];
        }

        $saved = DB::transaction(function () use ($topic, $payload, $data, $admin) {
            $saved = $topic ? tap($topic)->update($payload) : Topic::create($payload);
            $this->syncProposers($saved, $data['proposer_ids'] ?? [], $admin, false);

            return $saved;
        });

        if (! $topic) {
            SystemNotice::sendTo($saved->proposers()->get(), 'Topik ditambahkan admin',
                "Topik “{$saved->title}” ditambahkan admin ke bank topik dosen dengan Anda sebagai pengusul.", route('dosen.topics.index'));
        }
        ActivityLog::record($topic ? 'admin_update_topic' : 'admin_create_topic', $saved);

        return $saved;
    }

    private function lecturerTopicPayload(?Topic $topic, array $data, int $primaryId, int $periodId): array
    {
        if ($topic && $topic->applications()->where('status', 'diterima')->exists() && (int) $data['quota'] < $topic->acceptedCount()) {
            throw ValidationException::withMessages(['quota' => 'Kuota tidak boleh lebih kecil dari jumlah tim yang sudah diterima.']);
        }

        return [
            'period_id' => $periodId,
            'source' => 'dosen',
            'lecturer_id' => $primaryId,
            'title' => $data['title'],
            'description' => $data['description'],
            'competencies' => $data['competencies'],
            'specializations' => array_values($data['specializations']),
            'field' => $data['field'] ?? $topic?->field,
            'object' => $data['object'],
            'prodi_codes' => array_values($data['prodi_codes']),
            'quota' => (int) $data['quota'],
            'keyword_hits' => $this->keywordHits($data['title'].' '.$data['description'], 'dosen'),
        ];
    }

    /**
     * Pengusul utama selalu tercatat, begitu pula dosen yang mengisi formulir ($includeActor; bukan untuk admin);
     * dosen yang baru diundang menerima notifikasi.
     */
    private function syncProposers(Topic $topic, array $invitedIds, User $actor, bool $includeActor = true): void
    {
        $current = $topic->proposers()->pluck('users.id');
        $wanted = collect($invitedIds)->map(fn ($id) => (int) $id)->when($includeActor, fn ($c) => $c->push($actor->id))
            ->reject(fn ($id) => $id === $topic->lecturer_id)->unique()->values();

        $topic->proposers()->detach($current->reject(fn ($id) => $id === $topic->lecturer_id || $wanted->contains($id))->all());
        if (! $current->contains($topic->lecturer_id)) {
            $topic->proposers()->attach($topic->lecturer_id);
        }

        $new = $wanted->diff($current)->reject(fn ($id) => $id === $actor->id);
        $topic->proposers()->attach($new->mapWithKeys(fn ($id) => [$id => ['invited_by' => $actor->id]])->all());

        if ($new->isNotEmpty()) {
            SystemNotice::sendTo(
                User::query()->whereIn('id', $new)->get(),
                'Undangan pengusul topik',
                "Anda diinvite oleh {$actor->name} untuk menjadi pengusul pada topik dosen “{$topic->title}”.",
                route('dosen.topics.index')
            );
        }
        $topic->unsetRelation('proposers');
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
        $recipients = $topic->source === 'mandiri' ? $topic->team->activeUsers : $this->proposersOf($topic);
        $url = $topic->source === 'mandiri' ? route('mahasiswa.topic.index') : route('dosen.topics.index');
        SystemNotice::sendTo($recipients, $title, "“{$topic->title}”".($notes ? " — {$notes}" : ''), $url);
    }

    /**
     * F22 — tim melamar topik dosen. Paralel dengan topik mandiri (yang bersifat opsional): tidak perlu menunggu
     * topik mandiri diterima. Tim tanpa topik mandiri yang lamarannya ditolak belum memiliki topik final.
     */
    public function apply(Team $team, Topic $topic, string $portfolioUrl): TopicApplication
    {
        $errors = match (true) {
            $team->status !== 'diterima' => 'Registrasi tim belum diterima.',
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

        SystemNotice::sendTo($this->proposersOf($topic), 'Lamaran topik baru', "{$team->name} melamar “{$topic->title}”.", route('dosen.applications.index'));

        return $application;
    }

    public function withdraw(TopicApplication $application): void
    {
        if ($application->status !== 'menunggu') {
            throw ValidationException::withMessages(['topic' => 'Hanya lamaran yang menunggu yang dapat dibatalkan.']);
        }
        $application->update(['status' => 'dibatalkan', 'decided_at' => now()]);
    }

    /** Seluruh dosen pengusul topik dosen (pengusul utama + pengusul bersama). */
    private function proposersOf(Topic $topic): Collection
    {
        return $topic->proposers()->get()->push($topic->lecturer)->filter()->unique('id')->values();
    }

    /**
     * F43 — dosen pengusul (utama maupun bersama) menerima / menolak tim pelamar. Diterima → dosen yang memutuskan
     * otomatis menjadi pembimbing (PRD 6.4).
     */
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
                ['lecturer_id' => $lecturer->id, 'source' => 'sistem', 'assigned_by' => null]
            );
            LecturerProfile::grant($lecturer->id, 'pembimbing');
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

    /**
     * Admin menghapus topik dosen dari bank topik, termasuk yang sudah memiliki pelamar / tim diterima. Pengajuan tim
     * ikut terhapus; tim yang diterima kehilangan topik dosennya (topik final kembali ke topik mandiri yang diterima,
     * bila ada) beserta pembimbing yang ditetapkan otomatis dari topik ini. Tim terdampak dan dosen pengusul diberi tahu.
     *
     * @return int jumlah tim terdampak
     */
    public function deleteLecturerTopic(Topic $topic, User $admin): int
    {
        $applications = $topic->applications()->whereIn('status', ['menunggu', 'diterima'])->with('team.activeUsers')->get();
        $proposers = $this->proposersOf($topic);
        $title = $topic->title;

        DB::transaction(function () use ($topic, $applications, $proposers) {
            foreach ($applications->where('status', 'diterima') as $application) {
                TeamLecturer::query()->where('team_id', $application->team_id)->where('role', 'pembimbing')
                    ->where('source', 'sistem')->whereIn('lecturer_id', $proposers->pluck('id'))->delete();
            }
            $topic->delete(); // pengajuan tim ikut terhapus (cascade)
            foreach ($applications as $application) {
                $this->refreshFinalTopic($application->team);
            }
        });

        foreach ($applications as $application) {
            SystemNotice::sendTo(
                $application->team->activeUsers,
                'Topik dosen dihapus admin',
                $application->status === 'diterima'
                    ? "Topik dosen “{$title}” yang menjadi topik tim Anda dihapus admin. Tim Anda kini tidak memiliki topik dosen; silakan ajukan topik lain."
                    : "Topik dosen “{$title}” yang Anda ajukan dihapus admin, sehingga pengajuan tersebut dibatalkan.",
                route('mahasiswa.topic.index')
            );
        }
        SystemNotice::sendTo($proposers, 'Topik dihapus admin', "Topik “{$title}” dihapus admin dari bank topik dosen.", route('dosen.topics.index'));
        ActivityLog::record('admin_delete_topic', $admin, ['topic' => $title, 'teams' => $applications->pluck('team_id')->all()]);

        return $applications->count();
    }

    /** F8 — topik final: topik dosen yang diterima menggantikan topik mandiri. */
    public function refreshFinalTopic(Team $team): void
    {
        $accepted = $team->applications()->where('status', 'diterima')->latest('decided_at')->first();
        $independent = $team->topics()->where('source', 'mandiri')->where('status', 'diterima')->latest('id')->first();

        $team->update(['final_topic_id' => $accepted?->topic_id ?? $independent?->id]);
    }
}
