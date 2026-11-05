<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentVersion;
use App\Models\Period;
use App\Models\ReminderDispatch;
use App\Models\Team;
use App\Models\Topic;
use App\Models\TeamLecturer;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\ReminderService;
use App\Support\Todo;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** Ringkasan admin — titik masuk F11. */
class DashboardController extends Controller
{
    public function index(ReminderService $reminders): View
    {
        $period = Period::workspace()?->load('milestones');
        if (! $period) {
            return view('admin.dashboard', ['period' => null]);
        }

        $teams = Team::query()->where('period_id', $period->id);
        $statusCounts = (clone $teams)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $assessments = $reminders->incompleteAssessments($period);
        $actions = [
            'teams' => ['label' => 'Registrasi tim menunggu', 'count' => (int) ($statusCounts['menunggu'] ?? 0), 'route' => route('admin.teams.index', ['status' => 'menunggu'])],
            'topics' => ['label' => 'Topik menunggu approval', 'count' => Topic::query()->where('period_id', $period->id)->where('status', 'menunggu')->count(), 'route' => route('admin.topics.index', ['status' => 'menunggu'])],
            'supervisor' => ['label' => 'Tim belum memiliki pembimbing', 'count' => (clone $teams)->where('status', 'diterima')->whereDoesntHave('supervisor')->count(), 'route' => route('admin.plotting.index')],
            'examiner' => ['label' => 'Tim belum memiliki penguji', 'count' => (clone $teams)->where('status', 'diterima')->whereDoesntHave('examiner')->count(), 'route' => route('admin.plotting.index')],
            'sync' => ['label' => 'Sinkronisasi Drive gagal', 'count' => DocumentVersion::query()->where('sync_status', 'gagal')->count(), 'route' => route('admin.documents.index', ['status' => 'gagal'])],
            'reminders' => ['label' => 'Notifikasi gagal terkirim', 'count' => ReminderDispatch::query()->where('status', 'gagal')->count(), 'route' => route('admin.reminders.index', ['status' => 'gagal'])],
        ];

        return view('admin.dashboard', [
            'period' => $period,
            'registration' => $this->registration($period, $statusCounts),
            'lecturers' => $this->lecturerRoles($period),
            'todo' => $this->todo($period, $actions, $assessments),
        ]);
    }

    /**
     * Kartu "Registrasi Tim": status tim yang sudah diajukan (draft tidak dihitung) dan status tim seluruh
     * mahasiswa di sistem — tergabung (tim diterima), menunggu approval (tim menunggu), sisanya belum ada tim.
     */
    private function registration(Period $period, Collection $statusCounts): array
    {
        $teams = collect(['diterima', 'menunggu', 'ditolak'])->mapWithKeys(fn ($s) => [$s => (int) ($statusCounts[$s] ?? 0)]);

        $memberStatus = TeamMember::query()->where('team_members.invite_status', 'diterima')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->where('teams.period_id', $period->id)->whereIn('teams.status', ['diterima', 'menunggu'])
            ->selectRaw('teams.status, count(distinct team_members.user_id) as total')->groupBy('teams.status')
            ->pluck('total', 'teams.status');
        $studentTotal = User::query()->students()->count();
        $joined = (int) ($memberStatus['diterima'] ?? 0);
        $waiting = (int) ($memberStatus['menunggu'] ?? 0);

        return [
            'teams' => $teams,
            'teamTotal' => $teams->sum(),
            'students' => collect(['tergabung' => $joined, 'menunggu' => $waiting, 'belum' => max(0, $studentTotal - $joined - $waiting)]),
            'studentTotal' => $studentTotal,
        ];
    }

    /** Kartu "Status Dosen": peran tiap dosen di sistem pada tim periode ini (pembimbing, penguji, keduanya, belum ada). */
    private function lecturerRoles(Period $period): array
    {
        $roles = TeamLecturer::query()->whereHas('team', fn ($q) => $q->where('period_id', $period->id))
            ->get(['lecturer_id', 'role'])->groupBy('lecturer_id')
            ->map(fn ($rows) => $rows->pluck('role')->unique());
        $both = $roles->filter(fn ($r) => $r->contains('pembimbing') && $r->contains('penguji'))->count();
        $supervisor = $roles->filter(fn ($r) => $r->contains('pembimbing') && ! $r->contains('penguji'))->count();
        $examiner = $roles->filter(fn ($r) => $r->contains('penguji') && ! $r->contains('pembimbing'))->count();
        $total = User::query()->lecturers()->count();

        return [
            'counts' => ['keduanya' => $both, 'pembimbing' => $supervisor, 'penguji' => $examiner, 'belum' => max(0, $total - $both - $supervisor - $examiner)],
            'total' => $total,
        ];
    }

    /**
     * To Do admin: tindakan yang masih tertunda (hanya yang jumlahnya > 0), dengan deadline dari milestone
     * tahap terkait, serta penilaian dosen yang belum lengkap pada jadwal pengisian nilai yang sudah dibuka.
     */
    private function todo(Period $period, array $actions, Collection $assessments): Collection
    {
        $todo = collect();
        $add = function (string $key, string $title, string $detail, string $tone, string $icon, $due = null) use ($todo, $actions) {
            if ($actions[$key]['count'] > 0) {
                $todo->push(Todo::item($title, $detail, $actions[$key]['route'], $tone, $icon, $due));
            }
        };

        $add('teams', "Tinjau {$actions['teams']['count']} registrasi tim", 'Terima atau tolak pengajuan tim.', 'warn', 'heroicon-o-user-group', $period->milestoneOfKind('registrasi_tim')?->due_at);
        $add('topics', "Putuskan {$actions['topics']['count']} topik", 'Topik menunggu approval admin.', 'warn', 'heroicon-o-light-bulb', $period->milestoneOfKind('pengajuan_topik')?->due_at);
        $add('supervisor', "Plot pembimbing untuk {$actions['supervisor']['count']} tim", 'Tim diterima yang belum memiliki dosen pembimbing.', 'neutral', 'heroicon-o-arrows-right-left');
        $add('examiner', "Plot penguji untuk {$actions['examiner']['count']} tim", 'Tim diterima yang belum memiliki dosen penguji.', 'neutral', 'heroicon-o-arrows-right-left');

        foreach ($period->assessmentWindows()->get() as $window) {
            $rows = $assessments->where('component', $window->component);
            $pending = $rows->where('done', false)->count();
            if ($pending > 0 && now()->greaterThanOrEqualTo($window->opens_at)) {
                $todo->push(Todo::item('Pantau penilaian '.$window->label(), "{$pending} dari {$rows->count()} penilaian belum diisi dosen.",
                    route('admin.monitoring.assessments'), 'neutral', 'heroicon-o-bell-alert', $window->due_at));
            }
        }

        $add('sync', "Periksa {$actions['sync']['count']} sinkronisasi Drive gagal", 'Unggah ulang atau coba sinkronkan kembali.', 'bad', 'heroicon-o-cloud-arrow-up');
        $add('reminders', "Periksa {$actions['reminders']['count']} notifikasi gagal", 'Notifikasi otomatis yang gagal terkirim.', 'bad', 'heroicon-o-paper-airplane');

        return $todo;
    }
}
