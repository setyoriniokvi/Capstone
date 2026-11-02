<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LecturerProfile;
use App\Models\Period;
use App\Models\Team;
use App\Models\TeamLecturer;
use App\Models\User;
use App\Notifications\SystemNotice;
use App\Services\LoadBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** F10 — plotting pembimbing & penguji dengan dashboard load balancing. */
class PlottingController extends Controller
{
    public function index(Request $request, LoadBalanceService $balance): View
    {
        $period = Period::workspace();
        if (! $period) {
            return view('admin.plotting', ['period' => null]);
        }

        $rows = $balance->rows($period);
        $teams = Team::query()->where('period_id', $period->id)->where('status', 'diterima')
            ->when($request->input('filter') === 'tanpa_pembimbing', fn ($q) => $q->whereDoesntHave('supervisor'))
            ->when($request->input('filter') === 'tanpa_penguji', fn ($q) => $q->whereDoesntHave('examiner'))
            ->with(['finalTopic', 'supervisor.lecturer', 'examiner.lecturer'])
            ->orderBy('code')
            ->paginate(20)->withQueryString();

        return view('admin.plotting', [
            'period' => $period,
            'rows' => $rows,
            'teams' => $teams,
            'balance' => $balance,
            // Semua dosen aktif dapat diplot; hak akses Pembimbing/Penguji tercentang otomatis setelah diplot.
            'supervisors' => $rows,
            'examiners' => $rows,
        ]);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'in:pembimbing,penguji'],
            'lecturer_id' => ['required', Rule::exists('users', 'id')->where('role', 'dosen')->where('is_active', true)],
        ]);
        abort_unless($team->status === 'diterima', 409, 'Tim belum diterima.');

        $lecturer = User::query()->findOrFail($data['lecturer_id']);

        // Penguji ditentukan terpisah dari pembimbing demi objektivitas (PRD 6.4).
        $other = $data['role'] === 'pembimbing' ? 'penguji' : 'pembimbing';
        if ($team->lecturers()->where('role', $other)->where('lecturer_id', $lecturer->id)->exists()) {
            return back()->withErrors(['lecturer_id' => 'Pembimbing dan penguji harus dosen yang berbeda.']);
        }

        TeamLecturer::query()->updateOrCreate(
            ['team_id' => $team->id, 'role' => $data['role']],
            ['lecturer_id' => $lecturer->id, 'source' => 'admin', 'assigned_by' => $request->user()->id]
        );
        LecturerProfile::grant($lecturer->id, $data['role']);
        ActivityLog::record('plot_lecturer', $team, $data);

        SystemNotice::sendTo([$lecturer], "Penugasan sebagai {$data['role']}", $team->name, route($data['role'] === 'pembimbing' ? 'dosen.teams.supervised' : 'dosen.teams.examined', ['tim' => $team->id]));
        SystemNotice::sendTo($team->activeUsers, "Dosen {$data['role']} ditetapkan", $lecturer->name, route('mahasiswa.dashboard'));

        return back()->with('status', ucfirst($data['role'])." {$team->code} disimpan.");
    }

    public function destroy(TeamLecturer $assignment): RedirectResponse
    {
        $assignment->delete();
        ActivityLog::record('unplot_lecturer', $assignment->team, ['role' => $assignment->role]);

        return back()->with('status', 'Plotting dihapus.');
    }
}
