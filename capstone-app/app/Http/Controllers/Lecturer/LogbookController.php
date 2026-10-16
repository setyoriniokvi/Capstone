<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Logbook;
use App\Models\Team;
use App\Notifications\SystemNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F46 — approval log bimbingan oleh dosen pembimbing. */
class LogbookController extends Controller
{
    public function index(Request $request): View
    {
        $teams = Team::query()->whereHas('supervisor', fn ($q) => $q->where('lecturer_id', $request->user()->id))
            ->whereHas('period', fn ($q) => $q->where('is_active', true))
            ->with(['logbooks' => fn ($q) => $q->orderByRaw("case status when 'menunggu' then 0 when 'revisi' then 1 else 2 end")->orderBy('session_no')->with('author', 'histories.actor')])
            ->orderBy('code')->get();

        if ($request->filled('tim')) {
            $teams = $teams->where('id', $request->integer('tim'))->values();
        }

        return view('dosen.logbooks', [
            'teams' => $teams,
            'pendingTotal' => $teams->sum(fn ($t) => $t->logbooks->where('status', 'menunggu')->count()),
            'minLogbooks' => config('capstone.min_approved_logbooks'),
        ]);
    }

    public function review(Request $request, Logbook $logbook): RedirectResponse
    {
        abort_unless($logbook->team->supervisor?->lecturer_id === $request->user()->id, 403, 'Hanya dosen pembimbing tim ini yang dapat meninjau log.');
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'required_if:decision,reject', 'string', 'max:3000'],
        ], ['notes.required_if' => 'Tuliskan alasan / catatan revisi.']);

        if ($logbook->status !== 'menunggu') {
            return back()->withErrors(['logbook' => 'Log ini sudah ditinjau.']);
        }

        $approve = $data['decision'] === 'approve';
        $logbook->update([
            'status' => $approve ? 'disetujui' : 'revisi',
            'feedback' => $data['notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        $logbook->snapshot($approve ? 'disetujui' : 'revisi', $request->user());
        ActivityLog::record($approve ? 'approve_logbook' : 'revise_logbook', $logbook);

        $team = $logbook->team;
        $approved = $team->approvedLogbookCount();
        SystemNotice::sendTo(
            $team->activeUsers,
            $approve ? "Log sesi {$logbook->session_no} disetujui" : "Log sesi {$logbook->session_no} perlu revisi",
            $approve ? "{$approved} dari ".config('capstone.min_approved_logbooks').' sesi minimal telah disetujui.' : (string) $data['notes'],
            route('mahasiswa.logbooks.index')
        );

        return back()->with('status', $approve ? "Sesi {$logbook->session_no} {$team->code} disetujui." : 'Permintaan revisi dikirim ke tim.');
    }
}
