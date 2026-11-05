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
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * F10 — plotting pembimbing & penguji dalam dua tab. Hanya tim diterima yang sudah memiliki topik final (topik dosen
 * atau topik mandiri yang diterima) yang dapat diplot; tim yang topiknya masih menunggu tidak tampil.
 * - Plotting Tim Mahasiswa: daftar tim; "Plot dosen" per peran menawarkan dosen yang peminatannya sama dengan peminatan
 *   anggota tim (Edit / Hapus untuk dosen yang sudah ada, termasuk pembimbing otomatis dari topik dosen).
 * - Plotting Dosen: tabel load balancing (kuota, sisa, flag) dan "Detail Plottingan" per dosen — tim yang sudah diplot
 *   (Lepas) dan Tambah tim dari semua tim ber-topik final, boleh berbeda peminatan (mis. dosen peminatan tim sudah penuh).
 * Tambah & lepas memakai storeTeam / destroy; "return=dosen" kembali ke tab Plotting Dosen dengan modal dosen terbuka.
 */
class PlottingController extends Controller
{
    public function index(Request $request, LoadBalanceService $balance): View
    {
        $period = Period::workspace();
        $tab = $request->input('tab') === 'dosen' ? 'dosen' : 'tim';
        if (! $period) {
            return view('admin.plotting.index', ['period' => null, 'tab' => $tab]);
        }

        return view('admin.plotting.index', [
            'period' => $period,
            'tab' => $tab,
            'rows' => $balance->rows($period),
            'balance' => $balance,
            'teams' => $this->teams($period),
        ]);
    }

    /** Tetapkan / ganti satu dosen pada satu peran tim (tab Plotting Tim Mahasiswa & Tambah di Detail Plottingan). */
    public function storeTeam(Request $request, Team $team): RedirectResponse
    {
        $period = Period::workspace() ?? abort(409, 'Buka dashboard periode terlebih dahulu.');
        abort_unless($team->period_id === $period->id && $team->status === 'diterima' && $team->final_topic_id, 404);

        $data = $request->validate([
            'role' => ['required', 'in:pembimbing,penguji'],
            'lecturer_id' => ['required', Rule::exists('users', 'id')->where('role', 'dosen')->where('is_active', true)],
        ], ['lecturer_id.required' => 'Pilih dosen.']);
        $lecturer = User::query()->findOrFail($data['lecturer_id']);
        $fromLecturer = $request->input('return') === 'dosen';
        $back = fn (string $message) => back()->withErrors(['lecturer_id' => $message])->withInput()
            ->with($fromLecturer ? 'plot_lecturer' : 'plot_team', $fromLecturer ? $lecturer->id : "{$team->id}-{$data['role']}");

        // Penguji ditentukan terpisah dari pembimbing demi objektivitas (PRD 6.4).
        $other = $data['role'] === 'pembimbing' ? 'penguji' : 'pembimbing';
        if ($team->lecturers()->where('role', $other)->where('lecturer_id', $lecturer->id)->exists()) {
            return $back('Pembimbing dan penguji harus dosen yang berbeda.');
        }

        $this->assign($team, $data['role'], $lecturer, $request->user());

        return $this->backTo($request, $lecturer->id)->with('status', ucfirst($data['role'])." {$team->name} disimpan: {$lecturer->name}.");
    }

    /** Lepas dosen dari satu peran tim (tab Plotting Tim Mahasiswa). */
    public function destroy(Request $request, TeamLecturer $assignment): RedirectResponse
    {
        $assignment->delete();
        ActivityLog::record('unplot_lecturer', $assignment->team, ['role' => $assignment->role, 'lecturer_id' => $assignment->lecturer_id]);

        return $this->backTo($request, $assignment->lecturer_id)->with('status', ucfirst($assignment->role).' '.$assignment->team->name.' dihapus.');
    }

    /** Kembali ke tab asal: Plotting Dosen (modal dosen itu dibuka lagi) bila return=dosen, selain itu Plotting Tim Mahasiswa. */
    private function backTo(Request $request, int $lecturerId): RedirectResponse
    {
        return $request->input('return') === 'dosen'
            ? redirect()->route('admin.plotting.index', ['tab' => 'dosen'])->with('plot_lecturer', $lecturerId)
            : redirect()->route('admin.plotting.index');
    }

    /** Peminatan anggota tim (yang sudah bergabung), tanpa duplikat. */
    public static function teamSpecializations(Team $team): array
    {
        return $team->members->where('invite_status', 'diterima')->pluck('user.specialization')->filter()->unique()->values()->all();
    }

    /** Simpan plotting satu peran tim (menggantikan dosen sebelumnya), centang hak akses, catat log, dan beri notifikasi. */
    private function assign(Team $team, string $role, User $lecturer, User $admin): void
    {
        TeamLecturer::query()->updateOrCreate(
            ['team_id' => $team->id, 'role' => $role],
            ['lecturer_id' => $lecturer->id, 'source' => 'admin', 'assigned_by' => $admin->id]
        );
        LecturerProfile::grant($lecturer->id, $role);
        ActivityLog::record('plot_lecturer', $team, ['role' => $role, 'lecturer_id' => $lecturer->id]);

        SystemNotice::sendTo([$lecturer], "Penugasan sebagai {$role}", $team->name, route($role === 'pembimbing' ? 'dosen.teams.supervised' : 'dosen.teams.examined', ['tim' => $team->id]));
        SystemNotice::sendTo($team->activeUsers, "Dosen {$role} ditetapkan", $lecturer->name, route('mahasiswa.dashboard'));
    }

    /** Tim yang dapat diplot: tim diterima ber-topik final pada periode ini beserta anggota, pembimbing & pengujinya. */
    private function teams(Period $period): Collection
    {
        return Team::query()->where('period_id', $period->id)->where('status', 'diterima')->whereNotNull('final_topic_id')
            ->with(['members' => fn ($m) => $m->orderByDesc('is_leader'), 'members.user', 'finalTopic',
                'supervisor.lecturer', 'examiner.lecturer'])
            ->orderBy('name')->get();
    }
}
