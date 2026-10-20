<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentVersion;
use App\Models\Logbook;
use App\Models\Period;
use App\Models\ReminderDispatch;
use App\Models\Team;
use App\Models\Topic;
use App\Models\TopicApplication;
use App\Services\ReminderService;
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

        return view('admin.dashboard', [
            'period' => $period,
            'statusCounts' => $statusCounts,
            'teamTotal' => $statusCounts->sum(),
            'actions' => [
                ['label' => 'Registrasi tim menunggu', 'count' => (int) ($statusCounts['menunggu'] ?? 0), 'route' => route('admin.teams.index', ['status' => 'menunggu'])],
                ['label' => 'Topik menunggu approval', 'count' => Topic::query()->where('period_id', $period->id)->where('status', 'menunggu')->count(), 'route' => route('admin.topics.index', ['status' => 'menunggu'])],
                ['label' => 'Tim belum memiliki pembimbing', 'count' => (clone $teams)->where('status', 'diterima')->whereDoesntHave('supervisor')->count(), 'route' => route('admin.plotting.index', ['filter' => 'tanpa_pembimbing'])],
                ['label' => 'Tim belum memiliki penguji', 'count' => (clone $teams)->where('status', 'diterima')->whereDoesntHave('examiner')->count(), 'route' => route('admin.plotting.index', ['filter' => 'tanpa_penguji'])],
                ['label' => 'Sinkronisasi Drive gagal', 'count' => DocumentVersion::query()->where('sync_status', 'gagal')->count(), 'route' => route('admin.documents.index', ['status' => 'gagal'])],
                ['label' => 'Notifikasi gagal terkirim', 'count' => ReminderDispatch::query()->where('status', 'gagal')->count(), 'route' => route('admin.reminders.index', ['status' => 'gagal'])],
            ],
            'pipeline' => [
                'logbook_pending' => Logbook::query()->where('status', 'menunggu')->whereHas('team', fn ($q) => $q->where('period_id', $period->id))->count(),
                'applications_pending' => TopicApplication::query()->where('status', 'menunggu')->whereHas('topic', fn ($q) => $q->where('period_id', $period->id))->count(),
                'assessment_done' => $assessments->where('done', true)->count(),
                'assessment_total' => $assessments->count(),
            ],
        ]);
    }
}
