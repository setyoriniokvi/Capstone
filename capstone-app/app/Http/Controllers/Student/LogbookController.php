<?php

namespace App\Http\Controllers\Student;

use App\Models\ActivityLog;
use App\Models\Logbook;
use App\Notifications\SystemNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F27 — log bimbingan tim: tanggal + catatan bimbingan; status Menunggu / Disetujui / Ditolak (revisi). */
class LogbookController extends StudentController
{
    public function index(): View
    {
        $team = $this->team()?->load(['supervisor.lecturer', 'logbooks.author', 'logbooks.reviewer', 'logbooks.histories.actor']);

        return view('mahasiswa.logbooks', [
            'team' => $team,
            'approved' => $team?->logbooks->where('status', 'disetujui')->count() ?? 0,
            'minLogbooks' => config('capstone.min_approved_logbooks'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->teamOrFail();
        if (! $team->supervisor) {
            return back()->withErrors(['logbook' => 'Log bimbingan dapat diisi setelah dosen pembimbing ditetapkan.']);
        }

        $logbook = $team->logbooks()->create($this->validated($request) + [
            'session_no' => (int) $team->logbooks()->max('session_no') + 1,
            'created_by' => $request->user()->id,
            'status' => 'menunggu',
        ]);
        $logbook->snapshot('dibuat', $request->user());
        ActivityLog::record('submit_logbook', $logbook);
        SystemNotice::sendTo([$team->supervisor->lecturer], 'Log bimbingan baru', "{$team->name} · sesi {$logbook->session_no}", route('dosen.logbooks.index'));

        return back()->with('status', "Log sesi {$logbook->session_no} dikirim ke dosen pembimbing.");
    }

    public function update(Request $request, Logbook $logbook): RedirectResponse
    {
        $team = $this->teamOrFail();
        abort_unless($logbook->team_id === $team->id, 404);
        if ($logbook->status === 'disetujui') {
            return back()->withErrors(['logbook' => 'Log yang sudah disetujui tidak dapat diubah.']);
        }

        // Log yang ditolak (revisi) dikirim ulang ke pembimbing setelah diubah.
        $wasRejected = $logbook->status === 'revisi';
        $logbook->update($this->validated($request) + ['status' => 'menunggu']);
        $logbook->snapshot($wasRejected ? 'direvisi' : 'diubah', $request->user());

        if ($wasRejected && $team->supervisor) {
            SystemNotice::sendTo([$team->supervisor->lecturer], 'Log bimbingan diperbarui', "{$team->name} · sesi {$logbook->session_no}", route('dosen.logbooks.index'));
        }

        return back()->with('status', "Log sesi {$logbook->session_no} diperbarui".($wasRejected ? ' dan dikirim ulang ke pembimbing.' : '.'));
    }

    public function destroy(Logbook $logbook): RedirectResponse
    {
        abort_unless($logbook->team_id === $this->teamOrFail()->id, 404);
        if ($logbook->status === 'disetujui') {
            return back()->withErrors(['logbook' => 'Log yang sudah disetujui tidak dapat dihapus.']);
        }
        $logbook->delete();

        return back()->with('status', 'Log bimbingan dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'session_date' => ['required', 'date', 'before_or_equal:today'],
            'activity' => ['required', 'string', 'max:3000'],
        ], [
            'session_date.before_or_equal' => 'Tanggal bimbingan tidak boleh di masa depan.',
            'activity.required' => 'Isi catatan bimbingan.',
        ]);
    }
}
