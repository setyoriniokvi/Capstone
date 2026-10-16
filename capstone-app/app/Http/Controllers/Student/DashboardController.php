<?php

namespace App\Http\Controllers\Student;

use App\Models\Period;
use App\Services\ScreeningService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F19 — dashboard tim mahasiswa. */
class DashboardController extends StudentController
{
    public function index(Request $request, ScreeningService $screening): View
    {
        $user = $request->user();
        $period = Period::active()?->load('milestones', 'registrationRules.courses');
        $team = $period ? $user->teamFor($period)?->load(['members.user', 'finalTopic', 'supervisor.lecturer', 'examiner.lecturer', 'documents' => fn ($q) => $q->where('state', 'aktif')]) : null;
        $rule = $period?->registrationRules->firstWhere('prodi_code', $user->prodi_code);

        return view('mahasiswa.dashboard', [
            'period' => $period,
            'team' => $team,
            'eligibility' => $screening->eligibility($user, $rule),
            'logbook' => $team ? [
                'approved' => $team->logbooks()->where('status', 'disetujui')->count(),
                'pending' => $team->logbooks()->where('status', 'menunggu')->count(),
                'revision' => $team->logbooks()->where('status', 'revisi')->count(),
            ] : null,
            'invitations' => $user->memberships()->where('invite_status', 'diundang')->with('team.leader')->get(),
            'minLogbooks' => config('capstone.min_approved_logbooks'),
        ]);
    }
}
