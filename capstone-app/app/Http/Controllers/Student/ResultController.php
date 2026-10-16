<?php

namespace App\Http\Controllers\Student;

use App\Models\PeerReview;
use App\Services\ScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F29 — nilai & feedback; nilai akhir terbuka setelah peer review diisi (F28). */
class ResultController extends StudentController
{
    public function index(Request $request, ScoreCalculator $calculator): View
    {
        $user = $request->user();
        $team = $this->team()?->load(['assessments' => fn ($q) => $q->where('status', 'final')->with('assessor', 'scores.indicator'), 'activeUsers']);

        $peerDone = false;
        if ($team) {
            $expected = ($team->activeUsers->count() - 1) * count(config('capstone.peer_aspects'));
            $peerDone = $expected > 0 && PeerReview::query()->where('reviewer_id', $user->id)->where('team_id', $team->id)->count() >= $expected;
        }

        $result = $team && $peerDone ? $calculator->forTeam($team) : null;

        return view('mahasiswa.results', [
            'team' => $team,
            'peerDone' => $peerDone,
            'assessments' => $team?->assessments->sortBy(fn ($a) => array_search($a->component, array_keys(config('capstone.components'))))->values() ?? collect(),
            'mine' => $result ? $result['rows']->first(fn ($r) => $r['student']->id === $user->id) : null,
            'complete' => $result['complete'] ?? false,
            'missing' => $result['missing'] ?? [],
            'clos' => $calculator->clos(),
            'calculator' => $calculator,
        ]);
    }
}
