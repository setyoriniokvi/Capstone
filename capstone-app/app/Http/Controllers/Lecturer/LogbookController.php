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

/**
 * F46 — log bimbingan untuk dosen pembimbing. Tab "Pengajuan Log Bimbingan": log menunggu keputusan (setujui/tolak);
 * tab "Log Bimbingan": seluruh log tim bimbingan, disaring per tim dan pencarian di browser.
 */
class LogbookController extends Controller
{
    public function index(Request $request): View
    {
        $teams = Team::query()->whereHas('supervisor', fn ($q) => $q->where('lecturer_id', $request->user()->id))
            ->whereHas('period', fn ($q) => $q->where('is_active', true))
            ->with(['members' => fn ($q) => $q->where('invite_status', 'diterima')->with('user:id,name')])
            ->withCount(['logbooks as approved_count' => fn ($q) => $q->where('status', 'disetujui')])
            ->orderBy('name')->get(['teams.id', 'teams.name']);
        $tab = $request->query('tab') === 'log' ? 'log' : 'pengajuan';

        return view('dosen.logbooks', [
            'tab' => $tab,
            'teams' => $teams,
            'logs' => $tab === 'log' ? Logbook::query()->whereIn('team_id', $teams->pluck('id'))
                ->latest('session_date')->latest('id')->get() : collect(),
            // Log yang menunggu keputusan dari semua tim bimbingan, yang terlama diajukan lebih dulu
            'pending' => Logbook::query()->where('status', 'menunggu')->whereIn('team_id', $teams->pluck('id'))
                ->with('team:id,name', 'histories')->oldest('updated_at')->get(),
            'minLogbooks' => config('capstone.min_approved_logbooks'),
        ]);
    }

    public function review(Request $request, Logbook $logbook): RedirectResponse
    {
        abort_unless($logbook->team->supervisor?->lecturer_id === $request->user()->id, 403, 'Hanya dosen pembimbing tim ini yang dapat meninjau log.');
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

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
            $approve ? "{$approved} dari ".config('capstone.min_approved_logbooks').' sesi minimal telah disetujui.' : (($data['notes'] ?? '') ?: 'Perbaiki log lalu kirim ulang.'),
            route('mahasiswa.logbooks.index')
        );

        return back()->with('status', $approve ? "Sesi {$logbook->session_no} {$team->name} disetujui." : "Log sesi {$logbook->session_no} {$team->name} ditolak.");
    }
}
