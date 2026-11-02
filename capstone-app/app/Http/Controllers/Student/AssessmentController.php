<?php

namespace App\Http\Controllers\Student;

use App\Services\ScoreCalculator;
use Illuminate\View\View;

/**
 * F29 — menu Penilaian mahasiswa: nilai akhir per tahapan Capstone (bentuk penilaian yang dinilai dosen, mis. Proposal,
 * Laporan Progress, Sidang, Pameran) beserta penilai dan feedback. Nilai hanya tampil setelah dosen memfinalkannya;
 * peer review dilihat di menu Peer Review.
 */
class AssessmentController extends StudentController
{
    public function index(ScoreCalculator $calculator): View
    {
        $team = $this->team()?->load([
            'assessments' => fn ($q) => $q->where('status', 'final')->with('assessor'),
            'supervisor.lecturer', 'examiner.lecturer',
        ]);

        $stages = $team?->status === 'diterima'
            ? $calculator->componentSummary($team)->map(function (array $row) use ($team) {
                $final = $team->assessments->firstWhere('component', $row['component']);
                $lecturer = $row['role'] === 'penguji' ? $team->examiner?->lecturer : $team->supervisor?->lecturer;

                return $row + [
                    // Nilai tahapan dalam skala 0–100; "points" = sumbangannya ke nilai akhir (dari "max" poin).
                    'score' => $row['status'] === 'final' && $row['max'] > 0 ? round($row['points'] / $row['max'] * 100, 2) : null,
                    'assessor' => $final?->assessor ?? $lecturer,
                    'feedback' => $final?->feedback,
                    'submitted_at' => $final?->submitted_at ?? $final?->updated_at,
                ];
            })
            : collect();

        return view('mahasiswa.assessments', ['team' => $team, 'stages' => $stages, 'final' => $stages->isEmpty() ? null : $this->finalScore($calculator, $team)]);
    }

    /**
     * Nilai akhir mahasiswa yang masuk = Σ nilai CLO (penilaian dosen + peer review individual), sama dengan Rekap Nilai.
     * Hanya ditetapkan bila semua tahapan dosen final dan peer review dari rekan tim untuk mahasiswa ini lengkap.
     *
     * @return array{complete: bool, total: ?float, grade: ?array, pending: list<string>}
     */
    private function finalScore(ScoreCalculator $calculator, $team): array
    {
        $result = $calculator->forTeam($team);
        $mine = $result['rows']->first(fn (array $row) => $row['student']->id === auth()->id());
        $pending = collect($result['missing'])->reject(fn ($c) => $c === 'peer_review')
            ->map(fn ($c) => config("capstone.components.{$c}.label", $c))->values()->all();
        if ($calculator->indicatorsFor('peer_review')->isNotEmpty() && ! ($mine['peer_complete'] ?? false)) {
            $pending[] = 'Peer review dari rekan tim';
        }
        $complete = $mine && $pending === [];

        return [
            'complete' => $complete,
            'total' => $complete ? $mine['total'] : null,
            'grade' => $complete ? $calculator->gradeInfo($mine['total']) : null,
            'pending' => $pending,
        ];
    }
}
