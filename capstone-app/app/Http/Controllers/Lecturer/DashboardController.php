<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Logbook;
use App\Models\Period;
use App\Models\Team;
use App\Models\TopicApplication;
use App\Models\User;
use App\Services\LoadBalanceService;
use App\Services\ReminderService;
use App\Support\Todo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** F44 — dashboard dosen: sambutan, ringkasan, To Do, timeline, dan pengingat penilaian (tim yang belum dinilai per bentuk penilaian). */
class DashboardController extends Controller
{
    public function index(Request $request, ReminderService $reminders, LoadBalanceService $loads): View
    {
        $user = $request->user();
        $period = Period::active()?->load('milestones');

        $teams = $period ? Team::handledBy($user, $period) : collect();

        $pendingAssessments = $period ? $reminders->incompleteAssessments($period, $user->id)->where('done', false) : collect();

        // Pengingat penilaian: dikelompokkan per bentuk penilaian (urutan bentuk penilaian), tim urut nama.
        $order = array_flip(array_keys(config('capstone.components')));
        $reminderGroups = $pendingAssessments->sortBy(fn ($row) => mb_strtolower($row['team']->name))
            ->groupBy('component')->sortBy(fn ($rows, $component) => $order[$component] ?? PHP_INT_MAX);

        return view('dosen.dashboard', [
            'period' => $period,
            'reminderGroups' => $reminderGroups,
            'windows' => $period ? $period->assessmentWindows()->get()->keyBy('component') : collect(),
            // "Sebagian dinilai" bila sudah ada skor tersimpan
            'partial' => Assessment::query()->where('assessor_id', $user->id)->where('status', 'draft')->has('scores')->get()
                ->mapWithKeys(fn ($a) => ["{$a->team_id}:{$a->component}" => true]),
            'supervisedCount' => $teams->filter(fn ($t) => $t->lecturers->contains('role', 'pembimbing'))->count(),
            'examinedCount' => $teams->filter(fn ($t) => $t->lecturers->contains('role', 'penguji'))->count(),
            'counts' => [
                'applications' => TopicApplication::query()->where('status', 'menunggu')->whereHas('topic', fn ($q) => $q->proposedBy($user->id))->count(),
                'logbooks' => Logbook::query()->where('status', 'menunggu')->whereHas('team.supervisor', fn ($q) => $q->where('lecturer_id', $user->id))->count(),
                'assessments' => $pendingAssessments->count(),
            ],
            'minLogbooks' => Period::minLogbooks($period),
            'profile' => $user->lecturerProfile,
            // Kuota bimbingan/uji dari pengaturan admin (per dosen → kategori → global).
            'quota' => $period ? $loads->quotaFor($user, $period) : null,
            'todo' => $period ? $this->todo($user, $period, $pendingAssessments) : collect(),
        ]);
    }

    /**
     * To Do dosen, urut prioritas: nilai yang jendelanya terbuka (ada deadline), pengajuan topik yang menunggu
     * keputusan, dan log bimbingan yang menunggu persetujuan.
     */
    private function todo(User $user, Period $period, Collection $pendingAssessments): Collection
    {
        $todo = collect();
        $windows = $period->assessmentWindows()->get()->keyBy('component');

        foreach ($pendingAssessments as $row) {
            $window = $windows->get($row['component']);
            if (! $window?->isOpen()) {
                continue;
            }
            $todo->push(Todo::item(
                'Isi nilai '.config("capstone.components.{$row['component']}.label").' — '.$row['team']->name,
                'Penilaian sebagai '.$row['role'].'.',
                route('dosen.assessments.edit', [$row['team'], $row['component']]), 'warn', 'heroicon-o-pencil-square', $window->due_at,
            ));
        }

        $applications = TopicApplication::query()->where('status', 'menunggu')
            ->whereHas('topic', fn ($q) => $q->proposedBy($user->id)->where('period_id', $period->id))
            ->with('team', 'topic')->oldest()->get();
        $applyDue = $period->milestoneOfKind('apply_topik_dosen')?->due_at;
        foreach ($applications as $app) {
            $todo->push(Todo::item('Putuskan pengajuan '.$app->team->name, '“'.$app->topic->title.'”', route('dosen.applications.show', $app->topic), 'warn', 'heroicon-o-inbox-arrow-down', $applyDue));
        }

        $logs = Logbook::query()->where('status', 'menunggu')
            ->whereHas('team', fn ($q) => $q->where('period_id', $period->id))
            ->whereHas('team.supervisor', fn ($q) => $q->where('lecturer_id', $user->id))
            ->with('team')->get()->groupBy('team_id');
        foreach ($logs as $group) {
            $todo->push(Todo::item('Tinjau '.$group->count().' log bimbingan '.$group->first()->team->name, 'Setujui atau minta perbaikan.', route('dosen.logbooks.index'), 'neutral', 'icons.hand-book'));
        }

        return $todo;
    }
}
