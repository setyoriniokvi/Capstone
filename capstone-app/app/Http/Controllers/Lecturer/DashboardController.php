<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Logbook;
use App\Models\Period;
use App\Models\Team;
use App\Models\TopicApplication;
use App\Services\ReminderService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F44 — dashboard dosen: tim bimbingan & tim uji beserta progresnya. */
class DashboardController extends Controller
{
    public function index(Request $request, ReminderService $reminders): View
    {
        $user = $request->user();
        $period = Period::active()?->load('milestones');

        $teams = $period ? Team::query()->where('period_id', $period->id)
            ->whereHas('lecturers', fn ($q) => $q->where('lecturer_id', $user->id))
            ->with(['finalTopic', 'lecturers' => fn ($q) => $q->where('lecturer_id', $user->id), 'activeUsers'])
            ->withCount([
                'logbooks as logbook_approved' => fn ($q) => $q->where('status', 'disetujui'),
                'logbooks as logbook_pending' => fn ($q) => $q->where('status', 'menunggu'),
                'documents as document_count' => fn ($q) => $q->where('state', 'aktif'),
            ])
            ->orderBy('code')->get() : collect();

        $pendingAssessments = $period ? $reminders->incompleteAssessments($period, $user->id)->where('done', false) : collect();

        return view('dosen.dashboard', [
            'period' => $period,
            'supervised' => $teams->filter(fn ($t) => $t->lecturers->contains('role', 'pembimbing'))->values(),
            'examined' => $teams->filter(fn ($t) => $t->lecturers->contains('role', 'penguji'))->values(),
            'counts' => [
                'applications' => TopicApplication::query()->where('status', 'menunggu')->whereHas('topic', fn ($q) => $q->where('lecturer_id', $user->id))->count(),
                'logbooks' => Logbook::query()->where('status', 'menunggu')->whereHas('team.supervisor', fn ($q) => $q->where('lecturer_id', $user->id))->count(),
                'assessments' => $pendingAssessments->count(),
            ],
            'docMilestones' => $period?->milestones->where('kind', 'dokumen')->count() ?? 0,
            'minLogbooks' => config('capstone.min_approved_logbooks'),
            'profile' => $user->lecturerProfile,
        ]);
    }
}
