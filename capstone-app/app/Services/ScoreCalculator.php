<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Clo;
use App\Models\Indicator;
use App\Models\PeerReview;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * F14 / F18 / F29 — perhitungan nilai.
 *   nilai indikator = (Skor / 5) × Bobot(bentuk penilaian) × Faktor Pemahaman
 *   nilai CLO       = Σ nilai indikator milik CLO tersebut (poin dari total 100)
 *   nilai akhir     = Σ nilai CLO
 * CLO yang bersumber dari peer review bersifat individual (Alur §6.6).
 */
class ScoreCalculator
{
    private ?Collection $indicators = null;

    private ?Collection $clos = null;

    public function clos(): Collection
    {
        return $this->clos ??= Clo::query()->with('componentWeights')->orderBy('code')->get();
    }

    public function indicators(): Collection
    {
        return $this->indicators ??= Indicator::query()->with('componentWeights', 'clo')->orderBy('code')->get();
    }

    /** Indikator yang memiliki bobot pada bentuk penilaian tertentu. */
    public function indicatorsFor(string $component): Collection
    {
        return $this->indicators()->filter(fn (Indicator $i) => $i->weightFor($component) > 0)->values();
    }

    /** Bentuk penilaian yang dinilai dosen (bukan peer review) dan memiliki bobot. */
    public function lecturerComponents(): array
    {
        return collect(config('capstone.components'))
            ->reject(fn ($c) => $c['role'] === 'mahasiswa')
            ->keys()
            ->filter(fn ($key) => $this->indicatorsFor($key)->isNotEmpty())
            ->values()
            ->all();
    }

    public function indicatorValue(Indicator $indicator, string $component, int $score, float $factor = 1.0): float
    {
        return ($score / 5) * $indicator->weightFor($component) * ($indicator->usesOralFactor() ? $factor : 1.0);
    }

    /**
     * Nilai per mahasiswa dalam satu tim.
     *
     * @return array{rows: Collection, missing: list<string>, complete: bool}
     */
    public function forTeam(Team $team): array
    {
        $team->loadMissing(['activeUsers', 'assessments.scores', 'lecturers']);
        $finals = $team->assessments->where('status', 'final')->keyBy('component');
        $missing = [];

        $teamPoints = []; // clo_id => poin dari penilaian dosen
        foreach ($this->lecturerComponents() as $component) {
            /** @var Assessment|null $assessment */
            $assessment = $finals->get($component);
            if (! $assessment) {
                $missing[] = $component;

                continue;
            }
            foreach ($assessment->scores as $score) {
                $indicator = $this->indicators()->firstWhere('id', $score->indicator_id);
                if (! $indicator) {
                    continue;
                }
                $teamPoints[$indicator->clo_id] = ($teamPoints[$indicator->clo_id] ?? 0)
                    + $this->indicatorValue($indicator, $component, $score->score, $score->factor);
            }
        }

        $peerIndicators = $this->indicatorsFor('peer_review');
        $peer = PeerReview::query()->where('team_id', $team->id)->get();
        $members = $team->activeUsers;

        $rows = $members->map(function (User $student) use ($teamPoints, $peerIndicators, $peer, $members, &$missing) {
            $points = $teamPoints;
            $received = $peer->where('reviewee_id', $student->id);
            $expected = ($members->count() - 1) * count(config('capstone.peer_aspects'));
            $peerComplete = $expected > 0 && $received->count() >= $expected;

            if ($peerIndicators->isNotEmpty()) {
                if ($peerComplete) {
                    $avg = (float) $received->avg('score');
                    foreach ($peerIndicators as $indicator) {
                        $points[$indicator->clo_id] = ($points[$indicator->clo_id] ?? 0)
                            + ($avg / 5) * $indicator->weightFor('peer_review');
                    }
                } elseif (! in_array('peer_review', $missing, true)) {
                    $missing[] = 'peer_review';
                }
            }

            $perClo = $this->clos()->mapWithKeys(fn (Clo $clo) => [$clo->code => [
                'points' => round($points[$clo->id] ?? 0, 2),
                'percent' => $clo->weight > 0 ? round(($points[$clo->id] ?? 0) / $clo->weight * 100, 2) : 0,
            ]]);
            $total = round($perClo->sum('points'), 2);

            return [
                'student' => $student,
                'clos' => $perClo,
                'total' => $total,
                'grade' => $this->grade($total),
                'peer_complete' => $peerComplete,
            ];
        });

        return ['rows' => $rows, 'missing' => $missing, 'complete' => $missing === []];
    }

    public function grade(float $total): string
    {
        foreach (config('capstone.grade_scale') as $step) {
            if ($total >= $step['min']) {
                return $step['grade'];
            }
        }

        return '-';
    }

    /** Ringkasan bobot master data untuk validasi F13 (total 100). */
    public function weightSummary(): array
    {
        $components = array_keys(config('capstone.components'));
        $cloTotal = $this->clos()->sum('weight');
        $byComponent = [];
        foreach ($components as $component) {
            $byComponent[$component] = [
                'clo' => round($this->clos()->sum(fn (Clo $c) => $c->weightFor($component)), 2),
                'indicator' => round($this->indicators()->sum(fn (Indicator $i) => $i->weightFor($component)), 2),
            ];
        }

        return ['clo_total' => round($cloTotal, 2), 'components' => $byComponent];
    }
}
