<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Period;
use App\Models\Team;
use App\Notifications\SystemNotice;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F44 — daftar tim untuk pembimbing / penguji. Detail tim tampil sebagai overlay di halaman
 * "Tim Bimbingan" / "Tim Uji" (anggota, topik final, sesi bimbingan, submission, hasil penilaian).
 */
class TeamController extends Controller
{
    /** Semua tim yang dibimbing/diuji dosen pada periode aktif ("Lihat semua" dari dashboard). */
    public function index(Request $request): View
    {
        $period = Period::active();

        return view('dosen.teams', [
            'teams' => $period ? Team::handledBy($request->user(), $period) : collect(),
            'minLogbooks' => Period::minLogbooks($period),
        ]);
    }

    /**
     * Menu "Tim Bimbingan" (pembimbing) / "Tim Uji" (penguji): cari tim/anggota/topik + filter jenis topik.
     * ?tim={id} langsung membuka overlay detail tim tersebut (tautan notifikasi penugasan).
     */
    public function byRole(Request $request, string $role, ScoreCalculator $calculator): View
    {
        $period = Period::active();
        $q = trim((string) $request->query('q'));
        $jenis = in_array($request->query('jenis'), ['mandiri', 'dosen'], true) ? $request->query('jenis') : null;

        $teams = $period ? Team::query()->where('period_id', $period->id)
            ->whereHas('lecturers', fn ($l) => $l->where('lecturer_id', $request->user()->id)->where('role', $role))
            ->when($jenis, fn ($t) => $t->whereHas('finalTopic', fn ($f) => $f->where('source', $jenis)))
            ->when($q !== '', fn ($t) => $t->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhereHas('finalTopic', fn ($f) => $f->where('title', 'like', "%{$q}%"))
                ->orWhereHas('members', fn ($m) => $m->where('invite_status', 'diterima')
                    ->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('nim', 'like', "%{$q}%")))))
            ->with([
                'finalTopic', 'members' => fn ($m) => $m->where('invite_status', 'diterima')->orderByDesc('is_leader')->with('user'),
                'documents' => fn ($d) => $d->where('state', 'aktif')->with('currentVersion'),
                'assessments.scores', 'peerReviews:id,team_id,reviewer_id,reviewee_id,aspect,score',
            ])
            ->withCount(['logbooks as approved_logbooks' => fn ($l) => $l->where('status', 'disetujui')])
            ->orderBy('name')->get() : collect();
        // Peer review: anggota dianggap sudah mengisi bila semua aspek untuk semua rekan satu timnya terisi.
        $aspects = count(config('capstone.peer_aspects'));
        $teams->each(function (Team $team) use ($calculator, $aspects) {
            $team->setAttribute('assessment_summary', $calculator->componentSummary($team));
            $expected = max(0, $team->members->count() - 1) * $aspects;
            $filled = $team->peerReviews->groupBy('reviewer_id')->filter(fn ($rows) => $expected > 0 && $rows->count() >= $expected)->keys();
            $team->setAttribute('peer_summary', ['done' => $filled->count(), 'total' => $team->members->count(), 'filled' => $filled]);
        });

        return view('dosen.teams-by-role', [
            'role' => $role,
            'title' => $role === 'pembimbing' ? 'Tim Bimbingan' : 'Tim Uji',
            'teams' => $teams,
            'q' => $q,
            'jenis' => $jenis,
            'openTeam' => (int) $request->query('tim'),
            'docMilestones' => $period ? $period->milestones()->where('kind', 'dokumen')->orderBy('due_at')->get() : collect(),
            'minLogbooks' => Period::minLogbooks($period),
        ]);
    }

    /** F26 — status & feedback dosen atas dokumen milestone. */
    public function feedback(Request $request, Document $document): RedirectResponse
    {
        abort_unless($document->team->lecturerRole($request->user()), 403);
        $data = $request->validate([
            'review_status' => ['required', 'in:ditinjau,revisi,disetujui'],
            'feedback' => ['nullable', 'required_if:review_status,revisi', 'string', 'max:3000'],
        ], ['feedback.required_if' => 'Tuliskan catatan revisi untuk mahasiswa.']);

        $document->update($data + ['feedback_by' => $request->user()->id, 'feedback_at' => now()]);
        SystemNotice::sendTo([$document->owner], 'Feedback dokumen '.$document->typeLabel(), ucfirst($data['review_status']).($data['feedback'] ? ' — '.$data['feedback'] : ''), route('mahasiswa.documents.index'));

        return back()->with('status', 'Feedback dokumen disimpan.');
    }
}
