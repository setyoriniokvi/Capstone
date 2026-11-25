<?php

namespace App\Http\Controllers\Student;

use App\Services\ScoreCalculator;
use Illuminate\View\View;

/**
 * F29 — menu Penilaian mahasiswa: nilai akhir per tahapan Capstone (bentuk penilaian yang dinilai dosen, mis. Proposal,
 * Laporan Progress, Sidang, Pameran) beserta penilai dan feedback. Nilai hanya tampil setelah dosen memfinalkannya;
 * peer review dilihat di menu Peer Review. Nilai akhir & indeks tidak ditampilkan ke mahasiswa.
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

        return view('mahasiswa.assessments', ['team' => $team, 'stages' => $stages]);
    }

}
