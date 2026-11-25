<?php

namespace App\Services\MasterData;

use App\Models\Assessment;
use App\Models\DocumentVersion;
use App\Models\Logbook;
use App\Models\Period;
use App\Models\PeerReview;
use App\Models\Team;
use App\Models\TeamLecturer;
use App\Models\Topic;
use App\Models\TopicApplication;
use App\Models\User;
use App\Services\ScoreCalculator;
use Illuminate\Support\Collection;

/**
 * Baris kartu log proses di Master Data (periode yang dibuka admin). Tiap baris berbentuk array untuk
 * App\Support\ProcessTable: 'search', 'sort' => [...], 'filter' => [...], ditambah data tampilan.
 * Mahasiswa: pengajuan topik, log bimbingan, submission, peer review.  Dosen: status dosen, pengajuan topik dosen, penilaian.
 */
class ProcessLogs
{
    public function __construct(private readonly Period $period) {}

    private function teamIds(): Collection
    {
        return Team::query()->where('period_id', $this->period->id)->pluck('id');
    }

    private static function time($date): ?string
    {
        return $date?->format('Y-m-d H:i:s');
    }

    private static function lecturerName(?User $user): string
    {
        return $user?->name ?? '—';
    }

    /** Pengajuan topik mahasiswa: topik mandiri yang diajukan tim + lamaran tim ke topik dosen. */
    public function studentTopics(): Collection
    {
        $mandiri = Topic::query()->where('period_id', $this->period->id)->where('source', 'mandiri')->whereNotNull('team_id')
            ->with('team', 'reviewer')->get()
            ->map(fn (Topic $t) => [
                'team' => $t->team?->name ?? '—',
                'title' => $t->title,
                'type' => 'mandiri',
                'lecturer' => '—',
                'submitted' => $t->created_at,
                'status' => $t->status,
                'decided' => $t->reviewed_at,
                'decider' => $t->reviewer?->name,
                'notes' => $t->review_notes,
            ]);

        $applications = TopicApplication::query()->whereIn('team_id', $this->teamIds())
            ->with('team', 'topic.lecturer')->get();
        $deciders = User::query()->whereIn('id', $applications->pluck('decided_by')->filter())->pluck('name', 'id');
        $dosen = $applications->map(fn (TopicApplication $a) => [
            'team' => $a->team?->name ?? '—',
            'title' => $a->topic?->title ?? '—',
            'type' => 'dosen',
            'lecturer' => self::lecturerName($a->topic?->lecturer),
            'submitted' => $a->created_at,
            'status' => $a->status,
            'decided' => $a->decided_at,
            'decider' => $deciders[$a->decided_by] ?? null,
            'notes' => $a->notes,
        ]);

        return $mandiri->concat($dosen)->map(fn (array $r) => $r + [
            'search' => implode(' ', [$r['team'], $r['title'], $r['lecturer'], $r['notes']]),
            'sort' => ['tanggal' => self::time($r['submitted']), 'tim' => $r['team'], 'judul' => $r['title'], 'kategori' => $r['status']],
            'filter' => ['jenis' => $r['type'], 'status' => $r['status']],
        ])->values();
    }

    /**
     * Log Pengajuan Topik Dosen oleh Mahasiswa: pengajuan tim ke topik dosen yang sudah diputuskan (diterima / ditolak)
     * — dulu kartu "Riwayat" di halaman Approval Pengajuan Topik dosen.
     */
    public function lecturerTopicApplications(): Collection
    {
        $applications = TopicApplication::query()->whereIn('team_id', $this->teamIds())->whereIn('status', ['diterima', 'ditolak'])
            ->with('team', 'topic.lecturer')->get();
        $deciders = User::query()->whereIn('id', $applications->pluck('decided_by')->filter())->pluck('name', 'id');

        return $applications->map(fn (TopicApplication $a) => [
            'team' => $a->team?->name ?? '—',
            'title' => $a->topic?->title ?? '—',
            'lecturer' => self::lecturerName($a->topic?->lecturer),
            'submitted' => $a->created_at,
            'status' => $a->status,
            'decided' => $a->decided_at,
            'decider' => $deciders[$a->decided_by] ?? null,
            // Catatan hanya ada pada penolakan (alasan opsional)
            'notes' => $a->status === 'ditolak' ? $a->notes : null,
        ])->map(fn (array $r) => $r + [
            'search' => implode(' ', [$r['team'], $r['title'], $r['lecturer'], $r['decider'], $r['notes']]),
            'sort' => ['tanggal' => self::time($r['decided']), 'tim' => $r['team'], 'judul' => $r['title'], 'kategori' => $r['status']],
            'filter' => ['status' => $r['status']],
        ])->values();
    }

    /** Log bimbingan yang sudah diajukan (menunggu, disetujui, ditolak/revisi). */
    public function logbooks(): Collection
    {
        return Logbook::query()->whereIn('team_id', $this->teamIds())->where('status', '!=', 'draft')
            ->with('team', 'author', 'reviewer')->get()
            ->map(fn (Logbook $l) => [
                'team' => $l->team?->name ?? '—',
                'session' => $l->session_no,
                'date' => $l->session_date,
                'activity' => $l->activity,
                'author' => $l->author?->name,
                'reviewer' => $l->reviewer?->name,
                'status' => $l->status,
                'reviewed' => $l->reviewed_at,
                'feedback' => $l->feedback,
                'submitted' => $l->created_at,
            ])->map(fn (array $r) => $r + [
                'search' => implode(' ', [$r['team'], $r['activity'], $r['author'], $r['reviewer'], $r['feedback']]),
                'sort' => ['tanggal' => $r['date']?->format('Y-m-d').sprintf('-%03d', $r['session']), 'tim' => $r['team'], 'sesi' => $r['session'], 'kategori' => $r['status']],
                'filter' => ['status' => $r['status']],
            ]);
    }

    /** Submission: setiap file (revisi) yang diunggah mahasiswa. */
    public function submissions(): Collection
    {
        return DocumentVersion::query()
            ->whereHas('document', fn ($d) => $d->whereIn('team_id', $this->teamIds()))
            ->with('document.team', 'document.milestone', 'uploader')->get()
            ->map(fn (DocumentVersion $v) => [
                'team' => $v->document?->team?->name ?? '—',
                'type' => $v->document?->document_type,
                'typeLabel' => $v->document?->typeLabel() ?? '—',
                'milestone' => $v->document?->milestone?->name,
                'revision' => $v->revision_no,
                'file' => $v->stored_name ?: $v->original_name,
                'uploader' => $v->uploader?->name,
                'uploaded' => $v->created_at,
                'review' => $v->document?->review_status,
            ])->map(fn (array $r) => $r + [
                'search' => implode(' ', [$r['team'], $r['typeLabel'], $r['milestone'], $r['file'], $r['uploader']]),
                'sort' => ['tanggal' => self::time($r['uploaded']), 'tim' => $r['team'], 'kategori' => $r['typeLabel']],
                'filter' => ['dokumen' => $r['type'], 'review' => $r['review']],
            ]);
    }

    /** Peer review: satu baris per pasangan penilai → yang dinilai (skor semua aspek dirata-rata). */
    public function peerReviews(): Collection
    {
        $reviews = PeerReview::query()->whereIn('team_id', $this->teamIds())->get();
        $teams = Team::query()->whereIn('id', $reviews->pluck('team_id')->unique())->pluck('name', 'id');
        $users = User::query()->whereIn('id', $reviews->pluck('reviewer_id')->merge($reviews->pluck('reviewee_id'))->unique())->get()->keyBy('id');
        $aspects = count(config('capstone.peer_aspects', []));

        return $reviews->groupBy(fn (PeerReview $p) => "{$p->reviewer_id}-{$p->reviewee_id}")->map(function (Collection $group) use ($teams, $users, $aspects) {
            $first = $group->first();
            $reviewer = $users[$first->reviewer_id] ?? null;
            $reviewee = $users[$first->reviewee_id] ?? null;
            $complete = $aspects > 0 && $group->count() >= $aspects;

            return [
                'team' => $teams[$first->team_id] ?? '—',
                'reviewer' => $reviewer?->name ?? '—',
                'reviewerNim' => $reviewer?->nim,
                'reviewee' => $reviewee?->name ?? '—',
                'revieweeNim' => $reviewee?->nim,
                'average' => round((float) $group->avg('score'), 2),
                'aspects' => $group->count(),
                'expected' => $aspects,
                'status' => $complete ? 'lengkap' : 'parsial',
                'time' => $group->max('updated_at'),
            ];
        })->values()->map(fn (array $r) => $r + [
            'search' => implode(' ', [$r['team'], $r['reviewer'], $r['reviewerNim'], $r['reviewee'], $r['revieweeNim']]),
            'sort' => ['tanggal' => self::time($r['time']), 'tim' => $r['team'], 'nama' => $r['reviewer'], 'skor' => $r['average']],
            'filter' => ['tim' => $r['team'], 'status' => $r['status']],
        ]);
    }

    /** Status dosen: penetapan pembimbing / penguji tiap tim. */
    public function lecturerAssignments(): Collection
    {
        $rows = TeamLecturer::query()->whereIn('team_id', $this->teamIds())->with('team', 'lecturer.lecturerProfile')->get();
        $assigners = User::query()->whereIn('id', $rows->pluck('assigned_by')->filter())->pluck('name', 'id');

        return $rows->map(fn (TeamLecturer $a) => [
            'lecturer' => $a->lecturer?->name ?? '—',
            'code' => $a->lecturer?->lecturerProfile?->lecturer_code,
            'team' => $a->team?->name ?? '—',
            'role' => $a->role,
            'source' => $a->source,
            'assigner' => $assigners[$a->assigned_by] ?? null,
            'time' => $a->created_at,
        ])->map(fn (array $r) => $r + [
            'search' => implode(' ', [$r['lecturer'], $r['code'], $r['team'], $r['assigner']]),
            'sort' => ['tanggal' => self::time($r['time']), 'nama' => $r['lecturer'], 'tim' => $r['team'], 'kategori' => $r['role']],
            'filter' => ['peran' => $r['role'], 'sumber' => $r['source']],
        ]);
    }

    /** Pengajuan topik dosen (bank topik). */
    public function lecturerTopics(): Collection
    {
        return Topic::query()->where('period_id', $this->period->id)->where('source', 'dosen')
            ->with('lecturer', 'proposers', 'reviewer')->withCount(['applications as accepted_count' => fn ($q) => $q->where('status', 'diterima')])->get()
            ->map(fn (Topic $t) => [
                'lecturer' => $t->lecturer?->name ?? '—',
                'proposers' => $t->proposers->pluck('name')->reject(fn ($n) => $n === $t->lecturer?->name)->values()->all(),
                'title' => $t->title,
                'quota' => $t->quota,
                'accepted' => $t->accepted_count,
                'submitted' => $t->created_at,
                'status' => $t->status,
                'decided' => $t->reviewed_at,
                'decider' => $t->reviewer?->name,
                'notes' => $t->review_notes,
            ])->map(fn (array $r) => $r + [
                'search' => implode(' ', array_merge([$r['lecturer'], $r['title'], $r['notes']], $r['proposers'])),
                'sort' => ['tanggal' => self::time($r['submitted']), 'nama' => $r['lecturer'], 'judul' => $r['title'], 'kategori' => $r['status']],
                'filter' => ['status' => $r['status']],
            ]);
    }

    /** Penilaian dosen per bentuk penilaian (proposal, laporan progress, sidang, pameran). */
    public function assessments(ScoreCalculator $calculator): Collection
    {
        return Assessment::query()->whereIn('team_id', $this->teamIds())->with('team', 'assessor', 'scores')->get()
            ->map(function (Assessment $a) use ($calculator) {
                $indicators = $calculator->indicatorsFor($a->component);
                $points = $a->scores->sum(function ($score) use ($indicators, $calculator, $a) {
                    $indicator = $indicators->firstWhere('id', $score->indicator_id);

                    return $indicator ? $calculator->indicatorValue($indicator, $a->component, $score->score, (float) $score->factor) : 0;
                });

                return [
                    'lecturer' => $a->assessor?->name ?? '—',
                    'team' => $a->team?->name ?? '—',
                    'component' => $a->component,
                    'componentLabel' => $a->componentLabel(),
                    'role' => $a->assessor_role,
                    'status' => $a->status,
                    'points' => round($points, 2),
                    'max' => round($indicators->sum(fn ($i) => $i->weightFor($a->component)), 2),
                    'scored' => $a->scores->count(),
                    'indicators' => $indicators->count(),
                    'channel' => $a->channel,
                    'time' => $a->submitted_at ?? $a->updated_at,
                ];
            })->map(fn (array $r) => $r + [
                'search' => implode(' ', [$r['lecturer'], $r['team'], $r['componentLabel']]),
                'sort' => ['tanggal' => self::time($r['time']), 'nama' => $r['lecturer'], 'tim' => $r['team'], 'nilai' => $r['points'], 'kategori' => $r['componentLabel']],
                'filter' => ['bentuk' => $r['component'], 'status' => $r['status']],
            ]);
    }
}
