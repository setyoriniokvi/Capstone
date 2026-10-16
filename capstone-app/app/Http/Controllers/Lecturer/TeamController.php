<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Team;
use App\Notifications\SystemNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F44 — detail tim dan dokumen milestone untuk pembimbing / penguji. */
class TeamController extends Controller
{
    public function show(Request $request, Team $team): View
    {
        $role = $team->lecturerRole($request->user());
        abort_unless($role, 403, 'Anda bukan pembimbing atau penguji tim ini.');

        $team->load([
            'members.user', 'finalTopic.lecturer', 'supervisor.lecturer', 'examiner.lecturer', 'period.milestones',
            'documents' => fn ($q) => $q->where('state', 'aktif')->with(['owner', 'currentVersion', 'versions']),
            'logbooks',
        ]);

        return view('dosen.team', [
            'team' => $team,
            'role' => $role,
            'docMilestones' => $team->period->milestones->where('kind', 'dokumen')->values(),
            'minLogbooks' => config('capstone.min_approved_logbooks'),
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
