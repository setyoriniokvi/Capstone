<?php

namespace App\Services\MasterData;

use App\Models\Assessment;
use App\Models\AssessmentScore;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Indicator;
use App\Models\LecturerProfile;
use App\Models\LoadQuota;
use App\Models\Logbook;
use App\Models\MasterDataLog;
use App\Models\PeerReview;
use App\Models\Team;
use App\Models\TeamLecturer;
use App\Models\TeamMember;
use App\Models\Topic;
use App\Models\TopicApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Throwable;

/**
 * Master Data — mencatat otomatis setiap data mahasiswa / dosen yang dibuat, diubah (nilai sebelum & sesudah per kolom),
 * atau dihapus lewat Eloquent: akun, tim & anggota, topik, pengajuan topik, dokumen, log bimbingan, peer review,
 * plotting, kuota, profil dosen, dan penilaian. Contoh: admin membetulkan email mahasiswa yang salah ketik di menu
 * Mahasiswa → tercatat "Email: lama → baru" beserta siapa yang mengubah.
 * Catatan: perubahan massal lewat query builder (Model::where()->update()) tidak memicu event Eloquent sehingga tidak tercatat.
 */
class MasterDataLogger
{
    /** Kolom yang tidak dicatat di semua model (teknis / berubah sendiri). */
    private const IGNORED = ['id', 'created_at', 'updated_at', 'remember_token', 'last_login_at', 'avatar_url', 'google_id',
        'period_id', 'milestone_id', 'document_id', 'assessment_id', 'current_version_id'];

    /** Kolom teknis per model. */
    private const IGNORED_PER_MODEL = [
        DocumentVersion::class => ['stored_name', 'local_path', 'checksum', 'idempotency_key', 'sync_attempts', 'sync_error', 'synced_at', 'mime'],
        Document::class => ['drive_file_id', 'drive_folder_id'],
    ];

    /** Kolom ber-id user yang ditampilkan sebagai nama. */
    private const USER_FIELDS = ['user_id', 'lecturer_id', 'leader_id', 'reviewed_by', 'assessor_id', 'decided_by', 'reviewer_id',
        'reviewee_id', 'assigned_by', 'feedback_by', 'uploaded_by', 'created_by', 'owner_id'];

    private const LABELS = [
        'name' => 'Nama', 'email' => 'Email', 'role' => 'Peran', 'phone' => 'No. HP', 'is_active' => 'Aktif', 'nim' => 'NIM',
        'sks_passed' => 'SKS lulus', 'passed_courses' => 'MK lulus', 'origin_region' => 'Daerah asal', 'specialization' => 'Peminatan',
        'password' => 'Password', 'course_grades' => 'Nilai MK prasyarat', 'class_name' => 'Kelas',
        'lecturer_code' => 'Kode dosen', 'nip' => 'NIP', 'is_class_lecturer' => 'Dosen kelas', 'can_supervise' => 'Dapat membimbing', 'can_examine' => 'Dapat menguji',
        'team_id' => 'Tim', 'user_id' => 'Mahasiswa / dosen', 'is_leader' => 'Ketua', 'account_created' => 'Akun dibuat',
        'invite_status' => 'Status undangan', 'khs_path' => 'Berkas KHS', 'domicile_path' => 'Berkas domisili', 'responded_at' => 'Waktu respons',
        'leader_id' => 'Ketua', 'status' => 'Status', 'screening' => 'Hasil screening', 'screening_score' => 'Skor screening',
        'archived_members' => 'Anggota (arsip)', 'review_notes' => 'Catatan review', 'reviewed_by' => 'Direview oleh', 'reviewed_at' => 'Waktu review',
        'submitted_at' => 'Waktu kirim', 'final_topic_id' => 'Topik final', 'source' => 'Sumber', 'title' => 'Judul', 'description' => 'Deskripsi',
        'field' => 'Bidang', 'object' => 'Objek', 'object_detail' => 'Detail topik/objek', 'competencies' => 'Kompetensi',
        'specializations' => 'Peminatan', 'prodi_codes' => 'Prodi', 'lecturer_id' => 'Dosen', 'quota' => 'Kuota', 'keyword_hits' => 'Kata kunci terlarang',
        'topic_id' => 'Topik', 'motivation' => 'Motivasi', 'portfolio_url' => 'Portofolio', 'notes' => 'Catatan', 'decided_by' => 'Diputuskan oleh',
        'decided_at' => 'Waktu keputusan', 'owner_id' => 'Pemilik', 'owner_email' => 'Email pemilik', 'document_type' => 'Jenis dokumen', 'document_types' => 'Dokumen terkait',
        'current_revision' => 'Revisi', 'review_status' => 'Status review', 'feedback' => 'Feedback', 'feedback_by' => 'Feedback oleh',
        'feedback_at' => 'Waktu feedback', 'state' => 'Keadaan', 'revision_no' => 'Revisi ke-', 'original_name' => 'Nama file', 'size' => 'Ukuran (byte)',
        'sync_action' => 'Aksi sinkron', 'sync_status' => 'Status sinkron Drive', 'uploaded_by' => 'Diunggah oleh', 'session_no' => 'Sesi ke-',
        'session_date' => 'Tanggal sesi', 'activity' => 'Kegiatan', 'responsible_person' => 'Penanggung jawab', 'instrument' => 'Instrumen',
        'data_collected' => 'Data terkumpul', 'output' => 'Luaran', 'created_by' => 'Dibuat oleh', 'assessor_id' => 'Penilai',
        'component' => 'Komponen', 'assessor_role' => 'Peran penilai', 'channel' => 'Kanal', 'indicator_id' => 'Indikator', 'score' => 'Nilai',
        'oral_level' => 'Level lisan', 'factor' => 'Faktor', 'note' => 'Catatan', 'reviewer_id' => 'Penilai', 'reviewee_id' => 'Dinilai',
        'aspect' => 'Aspek', 'assigned_by' => 'Diplot oleh', 'scope' => 'Cakupan', 'category' => 'Kategori',
        'supervise_min' => 'Min. bimbingan', 'supervise_max' => 'Maks. bimbingan', 'examine_min' => 'Min. uji', 'examine_max' => 'Maks. uji',
    ];

    public static function register(): void
    {
        $logger = new self;
        foreach (['created', 'updated', 'deleted'] as $event) {
            Event::listen("eloquent.{$event}: *", function (string $name, array $payload) use ($logger, $event) {
                $model = $payload[0] ?? null;
                if ($model instanceof Model) {
                    $logger->log($event, $model);
                }
            });
        }
    }

    /** Model yang datanya dicatat di Master Data. */
    public const TRACKED = [User::class, LecturerProfile::class, LoadQuota::class, TeamLecturer::class, Assessment::class,
        AssessmentScore::class, Team::class, TeamMember::class, Topic::class, TopicApplication::class, Document::class,
        DocumentVersion::class, Logbook::class, PeerReview::class];

    /**
     * Pengisian awal: setiap data yang sudah ada di database tetapi belum punya log dicatat sebagai "Dibuat" (seluruh
     * nilainya) dengan waktu sesuai created_at data itu, sehingga Master Data memuat semua data — lama maupun baru.
     * Aman dijalankan berulang (data yang sudah punya log dilewati). Mengembalikan jumlah log yang ditambahkan.
     */
    public function backfill(): int
    {
        $added = 0;
        foreach (self::TRACKED as $class) {
            $model = new $class;
            $logged = MasterDataLog::query()->where('entity_type', $model->getMorphClass())->pluck('entity_id')->flip();
            $class::query()->chunkById(200, function ($rows) use ($logged, &$added) {
                foreach ($rows as $row) {
                    if (! $logged->has($row->getKey()) && $this->log('created', $row, 'Sistem (data awal)', $row->created_at ?? now())) {
                        $added++;
                    }
                }
            });
        }

        return $added;
    }

    public function log(string $event, Model $model, ?string $actorLabel = null, $at = null): bool
    {
        if ($model instanceof MasterDataLog) {
            return false;
        }
        try {
            $subject = $this->subject($model);
            if (! $subject) {
                return false;
            }
            $changes = $this->changes($event, $model);
            if ($event === 'updated' && $changes === []) {
                return false;
            }
            $actor = $actorLabel ? null : auth()->user();
            $log = new MasterDataLog([
                'audience' => $subject['audience'],
                'subject_user_id' => $subject['user']?->id,
                'subject_label' => Str::limit($subject['label'], 250),
                'entity' => $subject['entity'],
                'entity_type' => $model->getMorphClass(),
                'entity_id' => $model->getKey(),
                'event' => $event,
                'description' => isset($subject['description']) ? Str::limit($subject['description'], 250) : null,
                'changes' => $changes ?: null,
                'actor_id' => $actor?->id,
                'actor_label' => $actorLabel ?? ($actor ? $actor->name.' ('.$actor->role.')' : 'Sistem'),
            ]);
            if ($at) {
                $log->created_at = $at;
            }
            $log->save();

            return true;
        } catch (Throwable $e) {
            report($e); // log tidak boleh menggagalkan proses utama

            return false;
        }
    }

    /** @return array{audience: string, user: ?User, label: string, entity: string, description?: string}|null */
    private function subject(Model $m): ?array
    {
        return match (true) {
            $m instanceof User => match ($m->role) {
                'mahasiswa' => ['audience' => 'mahasiswa', 'user' => $m, 'label' => $this->person($m), 'entity' => 'Akun Mahasiswa'],
                'dosen' => ['audience' => 'dosen', 'user' => $m, 'label' => $this->person($m), 'entity' => 'Akun Dosen'],
                default => null,
            },
            $m instanceof LecturerProfile => $this->lecturer($this->user($m->user_id), 'Profil Dosen'),
            $m instanceof LoadQuota => $m->lecturer_id
                ? $this->lecturer($this->user($m->lecturer_id), 'Kuota Dosen')
                : ['audience' => 'dosen', 'user' => null, 'label' => 'Kuota '.$m->scope.($m->category ? ' · '.$m->category : ''), 'entity' => 'Kuota Dosen'],
            $m instanceof TeamLecturer => $this->lecturer($this->user($m->lecturer_id), 'Plotting', 'Tim '.$this->teamName($m->team_id).' · '.$m->role),
            $m instanceof Assessment => $this->lecturer($this->user($m->assessor_id), 'Penilaian', 'Tim '.$this->teamName($m->team_id).' · '.$m->component),
            $m instanceof AssessmentScore => $this->scoreSubject($m),
            $m instanceof Topic => $m->source === 'dosen'
                ? $this->lecturer($this->user($m->lecturer_id), 'Topik Dosen', $m->title)
                : $this->team($m->team_id, 'Topik Mandiri', $m->title),
            $m instanceof Team => ['audience' => 'mahasiswa', 'user' => null, 'label' => 'Tim '.$m->name, 'entity' => 'Tim'],
            $m instanceof TeamMember => $this->memberSubject($m),
            $m instanceof TopicApplication => $this->team($m->team_id, 'Pengajuan Topik', Topic::query()->whereKey($m->topic_id)->value('title')),
            $m instanceof Document => $this->team($m->team_id, 'Dokumen', $m->document_type),
            $m instanceof DocumentVersion => $this->versionSubject($m),
            $m instanceof Logbook => $this->team($m->team_id, 'Log Bimbingan', 'Sesi '.$m->session_no),
            $m instanceof PeerReview => $this->peerSubject($m),
            default => null,
        };
    }

    private function lecturer(?User $user, string $entity, ?string $description = null): array
    {
        return ['audience' => 'dosen', 'user' => $user, 'label' => $user ? $this->person($user) : '—', 'entity' => $entity, 'description' => $description];
    }

    private function team(?int $teamId, string $entity, ?string $description = null): array
    {
        return ['audience' => 'mahasiswa', 'user' => null, 'label' => 'Tim '.$this->teamName($teamId), 'entity' => $entity, 'description' => $description];
    }

    private function memberSubject(TeamMember $m): array
    {
        $user = $this->user($m->user_id);

        return ['audience' => 'mahasiswa', 'user' => $user, 'label' => $user ? $this->person($user) : '—', 'entity' => 'Anggota Tim',
            'description' => 'Tim '.$this->teamName($m->team_id)];
    }

    private function versionSubject(DocumentVersion $v): array
    {
        $document = Document::query()->find($v->document_id);

        return $this->team($document?->team_id, 'Revisi Dokumen', trim(($document?->document_type ?? 'Dokumen').' · revisi '.$v->revision_no));
    }

    private function peerSubject(PeerReview $p): array
    {
        $user = $this->user($p->reviewer_id);

        return ['audience' => 'mahasiswa', 'user' => $user, 'label' => $user ? $this->person($user) : '—', 'entity' => 'Peer Review',
            'description' => 'Menilai '.($this->user($p->reviewee_id)?->name ?? '—').' · '.$p->aspect];
    }

    private function scoreSubject(AssessmentScore $s): ?array
    {
        $assessment = Assessment::query()->find($s->assessment_id);
        if (! $assessment) {
            return null;
        }

        return $this->lecturer($this->user($assessment->assessor_id), 'Nilai Indikator',
            'Tim '.$this->teamName($assessment->team_id).' · '.$assessment->component.' · '.(Indicator::query()->whereKey($s->indicator_id)->value('code') ?? 'indikator'));
    }

    /** @return list<array{field: string, label: string, before: ?string, after: ?string}> */
    private function changes(string $event, Model $model): array
    {
        $ignored = array_merge(self::IGNORED, self::IGNORED_PER_MODEL[$model::class] ?? []);
        $keys = match ($event) {
            'updated' => array_keys($model->getChanges()),
            'created' => array_keys($model->getAttributes()),
            default => array_keys($model->getRawOriginal()),
        };
        $rows = [];
        foreach ($keys as $key) {
            if (in_array($key, $ignored, true)) {
                continue;
            }
            $before = $event === 'created' ? null : $this->format($model, $key, $model->getRawOriginal($key));
            $after = $event === 'deleted' ? null : $this->format($model, $key, $model->getAttributes()[$key] ?? null);
            if ($before === $after || ($event !== 'updated' && $before === null && $after === null)) {
                continue;
            }
            $rows[] = ['field' => $key, 'label' => self::LABELS[$key] ?? Str::headline($key), 'before' => $before, 'after' => $after];
        }

        return $rows;
    }

    private function format(Model $model, string $key, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($key === 'password') {
            return '(tersimpan)';
        }
        if (in_array($key, self::USER_FIELDS, true)) {
            return $this->user((int) $value)?->name ?? '#'.$value;
        }
        if ($key === 'team_id') {
            return $this->teamName((int) $value);
        }
        if (in_array($key, ['topic_id', 'final_topic_id'], true)) {
            return Topic::query()->whereKey($value)->value('title') ?? '#'.$value;
        }
        if ($key === 'indicator_id') {
            return Indicator::query()->whereKey($value)->value('code') ?? '#'.$value;
        }
        if ($model->hasCast($key, ['bool', 'boolean'])) {
            return $value ? 'Ya' : 'Tidak';
        }
        if (is_string($value) && $model->hasCast($key, ['array', 'json', 'collection', 'object', 'encrypted:array'])) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                if ($decoded === []) {
                    return null;
                }

                return Str::limit(array_is_list($decoded) && ! Arr::first($decoded, fn ($v) => is_array($v))
                    ? implode(', ', $decoded)
                    : json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1000);
            }
        }

        return Str::limit(is_scalar($value) ? (string) $value : json_encode($value), 1000);
    }

    private function person(User $user): string
    {
        $id = $user->role === 'mahasiswa' ? $user->nim : null;

        return $user->name.($id ? ' ('.$id.')' : '');
    }

    private function user(?int $id): ?User
    {
        return $id ? User::query()->find($id) : null;
    }

    private function teamName(?int $id): string
    {
        return ($id ? Team::query()->whereKey($id)->value('name') : null) ?? '—';
    }
}
