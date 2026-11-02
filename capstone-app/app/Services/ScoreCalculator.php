<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentComponent;
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
 * Bentuk penilaian yang dinilai pembimbing & penguji sekaligus: nilai keduanya wajib final, poinnya dirata-rata.
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
            ->reject(fn ($c) => in_array('mahasiswa', $c['roles'] ?? [$c['role']], true))
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
     * Hasil penilaian dosen per bentuk penilaian untuk satu tim: status (final / draft / belum) dan poin
     * yang diperoleh dari bobot maksimal komponen — memakai rumus yang sama dengan forTeam().
     *
     * @return Collection<int, array{component: string, label: string, role: string, status: string, points: float, max: float}>
     */
    public function componentSummary(Team $team): Collection
    {
        $team->loadMissing('assessments.scores');

        return collect($this->lecturerComponents())->map(function (string $component) use ($team) {
            $indicators = $this->indicatorsFor($component);
            $finals = $this->finalsFor($team, $component);
            $complete = ! $finals->contains(null);

            return [
                'component' => $component,
                'label' => config("capstone.components.{$component}.label"),
                'role' => config("capstone.components.{$component}.role"),
                'roles' => AssessmentComponent::rolesOf($component),
                'status' => match (true) {
                    $complete => 'final',
                    $team->assessments->contains('component', $component) => 'draft',
                    default => 'belum',
                },
                'points' => round($complete ? $finals->avg(fn (Assessment $a) => $this->assessmentPoints($a, $component, $indicators)) : 0, 2),
                'max' => round($indicators->sum(fn (Indicator $i) => $i->weightFor($component)), 2),
            ];
        });
    }

    /**
     * Nilai per mahasiswa dalam satu tim.
     *
     * @return array{rows: Collection, missing: list<string>, complete: bool}
     */
    public function forTeam(Team $team): array
    {
        $team->loadMissing(['activeUsers', 'assessments.scores', 'lecturers']);
        $missing = [];

        $teamPoints = []; // clo_id => poin dari penilaian dosen
        foreach ($this->lecturerComponents() as $component) {
            $finals = $this->finalsFor($team, $component);
            if ($finals->contains(null)) {
                $missing[] = $component;

                continue;
            }
            // Dinilai beberapa penilai → poin tiap penilai dirata-rata.
            foreach ($finals as $assessment) {
                foreach ($assessment->scores as $score) {
                    $indicator = $this->indicators()->firstWhere('id', $score->indicator_id);
                    if (! $indicator) {
                        continue;
                    }
                    $teamPoints[$indicator->clo_id] = ($teamPoints[$indicator->clo_id] ?? 0)
                        + $this->indicatorValue($indicator, $component, $score->score, $score->factor) / $finals->count();
                }
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

    /**
     * Penilaian final per penilai bentuk penilaian (null bila penilai tersebut belum final).
     *
     * @return Collection<int, ?Assessment>
     */
    private function finalsFor(Team $team, string $component): Collection
    {
        $finals = $team->assessments->where('component', $component)->where('status', 'final');
        $roles = AssessmentComponent::rolesOf($component);

        // Satu penilai: penilaian final mana pun untuk bentuk ini (data lama mungkin tanpa kecocokan peran).
        return count($roles) <= 1
            ? collect([$finals->first()])
            : collect($roles)->map(fn ($role) => $finals->firstWhere('assessor_role', $role))->values();
    }

    private function assessmentPoints(Assessment $assessment, string $component, Collection $indicators): float
    {
        return $assessment->scores->sum(function ($score) use ($component, $indicators) {
            $indicator = $indicators->firstWhere('id', $score->indicator_id);

            return $indicator ? $this->indicatorValue($indicator, $component, $score->score, $score->factor) : 0;
        });
    }

    public function grade(float $total): string
    {
        return $this->gradeInfo($total)['grade'];
    }

    /**
     * Indeks nilai dari nilai akhir (NSM 0–100): A bila NSM > 85, AB bila 75 < NSM ≤ 85, dst. (config capstone.grade_scale).
     *
     * @return array{grade: string, point: float, label: string}
     */
    public function gradeInfo(float $total): array
    {
        $scale = config('capstone.grade_scale');
        $step = collect($scale)->first(fn (array $s) => $s['above'] === null || $total > $s['above']) ?? end($scale);

        return ['grade' => $step['grade'], 'point' => (float) $step['point'], 'label' => $step['label']];
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
