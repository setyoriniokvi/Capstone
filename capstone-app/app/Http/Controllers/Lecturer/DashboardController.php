<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Logbook;
use App\Models\Period;
use App\Models\Team;
use App\Models\TopicApplication;
use App\Models\User;
use App\Services\LoadBalanceService;
use App\Services\ReminderService;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/** F44 — dashboard dosen: sambutan, ringkasan, timeline, dan 3 tim dengan aktivitas terbaru. */
class DashboardController extends Controller
{
    public function index(Request $request, ReminderService $reminders, LoadBalanceService $loads): View
    {
        $user = $request->user();
        $period = Period::active()?->load('milestones');

        $teams = $period ? Team::handledBy($user, $period) : collect();

        $pendingAssessments = $period ? $reminders->incompleteAssessments($period, $user->id)->where('done', false) : collect();

        return view('dosen.dashboard', [
            'period' => $period,
            'recentTeams' => $teams->take(3),
            'supervisedCount' => $teams->filter(fn ($t) => $t->lecturers->contains('role', 'pembimbing'))->count(),
            'examinedCount' => $teams->filter(fn ($t) => $t->lecturers->contains('role', 'penguji'))->count(),
            'counts' => [
                'applications' => TopicApplication::query()->where('status', 'menunggu')->whereHas('topic', fn ($q) => $q->where('lecturer_id', $user->id))->count(),
                'logbooks' => Logbook::query()->where('status', 'menunggu')->whereHas('team.supervisor', fn ($q) => $q->where('lecturer_id', $user->id))->count(),
                'assessments' => $pendingAssessments->count(),
            ],
            'minLogbooks' => config('capstone.min_approved_logbooks'),
            'profile' => $user->lecturerProfile,
            // Kuota bimbingan/uji dari pengaturan admin (per dosen → kategori → global).
            'quota' => $period ? $loads->quotaFor($user, $period) : null,
            'todo' => $period ? $this->todo($user, $period, $pendingAssessments) : collect(),
        ]);
    }

    /**
     * To Do dosen, urut prioritas: nilai yang jendelanya terbuka (bertenggat), pengajuan topik yang menunggu
     * keputusan, log bimbingan yang menunggu persetujuan, dan dokumen tim bimbingan yang belum diberi feedback.
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
            $todo->push($this->item(
                'Isi nilai '.config("capstone.components.{$row['component']}.label").' — '.$row['team']->name,
                'Penilaian sebagai '.$row['role'].'.',
                route('dosen.assessments.edit', [$row['team'], $row['component']]), 'warn', 'heroicon-o-pencil-square', $window->due_at,
            ));
        }

        $applications = TopicApplication::query()->where('status', 'menunggu')
            ->whereHas('topic', fn ($q) => $q->where('lecturer_id', $user->id)->where('period_id', $period->id))
            ->with('team', 'topic')->oldest()->get();
        $applyDue = $period->milestoneOfKind('apply_topik_dosen')?->due_at;
        foreach ($applications as $app) {
            $todo->push($this->item('Putuskan pengajuan '.$app->team->name, '“'.$app->topic->title.'”', route('dosen.applications.index'), 'warn', 'heroicon-o-inbox-arrow-down', $applyDue));
        }

        $logs = Logbook::query()->where('status', 'menunggu')
            ->whereHas('team', fn ($q) => $q->where('period_id', $period->id))
            ->whereHas('team.supervisor', fn ($q) => $q->where('lecturer_id', $user->id))
            ->with('team')->get()->groupBy('team_id');
        foreach ($logs as $group) {
            $todo->push($this->item('Tinjau '.$group->count().' log bimbingan '.$group->first()->team->name, 'Setujui atau minta perbaikan.', route('dosen.logbooks.index'), 'neutral', 'icons.hand-book'));
        }

        $documents = Document::query()->where('state', 'aktif')->where('review_status', 'dikumpulkan')
            ->whereHas('team', fn ($q) => $q->where('period_id', $period->id))
            ->whereHas('team.supervisor', fn ($q) => $q->where('lecturer_id', $user->id))
            ->with('team')->get();
        foreach ($documents as $doc) {
            $todo->push($this->item('Beri feedback '.$doc->typeLabel().' — '.$doc->team->name, 'Dokumen sudah dikumpulkan tim.', route('dosen.teams.show', $doc->team), 'neutral', 'heroicon-o-chat-bubble-left-ellipsis'));
        }

        return $todo;
    }

    private function item(string $title, string $detail, string $url, string $tone, string $icon, ?CarbonInterface $due = null): array
    {
        $urgency = match (true) {
            ! $due => null,
            $due->isPast() => 'late',
            $due->lessThanOrEqualTo(now()->addDays(config('capstone.dashboard_soon_days'))) => 'soon',
            default => null,
        };

        return compact('title', 'detail', 'url', 'tone', 'icon', 'due', 'urgency');
    }
}
