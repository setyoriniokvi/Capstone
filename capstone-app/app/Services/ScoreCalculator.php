<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentComponent;
use App\Models\Clo;
use App\Models\Indicator;
use App\Models\PeerReview;
use App\Models\ScoreRule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * F14 / F18 / F29 — perhitungan nilai (sheet "Indikator Draft -> Igadis").
 *   nilai indikator = (Nilai level ÷ 100) × Bobot(bentuk penilaian) × Faktor Pemahaman
 *     Nilai level = nilai tingkat Rubrik Dokumen (5 = 95, 4 = 80, 3 = 70, 2 = 50, 1 = 40, 0 = 0); rumus per bentuk
 *     penilaian dapat diganti admin ke (Interval ÷ Interval maksimum) atau (Interval ÷ angka tetap).
 *     Faktor Pemahaman: dari Rubrik Lisan (per tim) untuk skema yang diatur admin (ScoreRule), selain itu faktor bawaan.
 *   nilai iGadis    = Σ nilai indikator satu bentuk penilaian × CLO ÷ bobot kolomnya × 100 (rata-rata berbobot, 0–100)
 *   nilai CLO       = Σ nilai indikator milik CLO tersebut (poin dari total 100)
 *   nilai akhir     = Σ nilai CLO; bila ada Penyesuaian Nilai pembimbing (dan nilai lengkap) → nilai disesuaikan
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
        return $this->indicators ??= Indicator::query()->with('componentWeights', 'clo', 'rubric')->orderBy('code')->get();
    }

    /** Indikator yang memiliki bobot pada bentuk penilaian tertentu. */
    public function indicatorsFor(string $component): Collection
    {
        return $this->indicators()->filter(fn (Indicator $i) => $i->weightFor($component) > 0)->values();
    }

    /** Bentuk penilaian yang dinilai dosen (bukan peer review) dan memiliki bobot. */
    public function lecturerComponents(): array
    {
        return collect(AssessmentComponent::lecturerConfig())
            ->keys()
            ->filter(fn ($key) => $this->indicatorsFor($key)->isNotEmpty())
            ->values()
            ->all();
    }

    public function indicatorValue(Indicator $indicator, string $component, int $score, float $factor = 1.0): float
    {
        $parts = $this->indicatorParts($indicator, $component, $score, $factor);

        return $parts['base'] * $parts['factor'];
    }

    /**
     * Rincian nilai indikator: ratio = nilai dokumen 0–1 (Nilai level ÷ 100, atau Interval ÷ pembagi),
     * base = nilai sebelum Faktor Pemahaman (ratio × Bobot × pengali), factor = Faktor Pemahaman yang dipakai
     * (1 bila rumus bentuk penilaian ini tanpa faktor). Nilai = base × factor.
     *
     * @return array{ratio: float, base: float, factor: float}
     */
    public function indicatorParts(Indicator $indicator, string $component, int $score, float $factor = 1.0): array
    {
        // Rumus bentuk penilaian ini (diatur admin per periode di halaman Indikator)
        $f = AssessmentComponent::formulaOf($component);
        $max = $indicator->maxInterval();
        $level = min($score, $max);
        $ratio = match ($f['divisor']) {
            'level' => AssessmentComponent::levelValue($level) / 100,
            'max' => $level / $max,
            default => $level / $f['fixed'],
        };
        $weight = $f['weight'] ? $indicator->weightFor($component) : 1;
        $understanding = $f['oral'] ? ($indicator->usesOralFactor() ? $factor : ScoreRule::defaultFactor()) : 1;

        return ['ratio' => $ratio, 'base' => $ratio * $weight * $f['multiplier'], 'factor' => (float) $understanding];
    }

    /**
     * Kolom Raw Data Penilaian: setiap bentuk penilaian (urut konfigurasi, termasuk peer review) dengan CLO yang punya
     * indikator berbobot pada bentuk itu.
     *
     * oral[kode CLO] = ada indikator CLO itu yang memakai Faktor Lisan pada bentuk ini (kolom Lisan hanya tampil bila ya).
     *
     * @return Collection<string, array{label: string, peer: bool, clos: list<string>, igadis: array<string, ?string>, oral: array<string, bool>}>
     */
    public function rawColumns(): Collection
    {
        return collect(config('capstone.components'))->map(function (array $meta, string $component) {
            $indicators = $this->indicatorsFor($component);
            $clos = $this->clos()->whereIn('id', $indicators->pluck('clo_id')->unique());

            return [
                'label' => $meta['label'],
                'peer' => AssessmentComponent::isPeerKey($component),
                'clos' => $clos->pluck('code')->values()->all(),
                // Kode CLO iGadis (PLOxx-CLOyy) per kode CLO capstone
                'igadis' => $clos->pluck('igadis_code', 'code')->all(),
                'oral' => $clos->mapWithKeys(fn (Clo $clo) => [$clo->code => $indicators
                    ->contains(fn (Indicator $i) => $i->clo_id === $clo->id && $i->usesOralFactor($component))])->all(),
            ];
        })->filter(fn (array $c) => $c['clos'] !== []);
    }

    /**
     * Raw Data Penilaian satu tim, per mahasiswa. Bentuk penilaian dosen (hanya penilaian final; beberapa penilai dirata-rata):
     *   per CLO  base (Σ nilai indikator sebelum Faktor Pemahaman), lisan (faktor efektif = total ÷ base), total (Σ nilai indikator)
     *   total    Σ total CLO
     * Peer review (per mahasiswa, bila lengkap): per CLO average (rata-rata nilai 0–90) dan total = (rata-rata ÷ 100) × bobot; total Σ.
     *
     * @return Collection<int, array{student: User, components: array<string, array{ready: bool, clos: array, total: float}>}>
     */
    public function rawForTeam(Team $team): Collection
    {
        $team->loadMissing(['activeUsers', 'assessments.scores']);
        $columns = $this->rawColumns();
        $codeOf = fn (int $cloId) => $this->clos()->firstWhere('id', $cloId)?->code;

        $teamComponents = [];
        foreach ($columns->reject(fn ($c) => $c['peer']) as $component => $meta) {
            $finals = $this->finalsFor($team, $component);
            if ($finals->contains(null)) {
                $teamComponents[$component] = ['ready' => false, 'clos' => [], 'total' => 0.0];

                continue;
            }
            $clos = [];
            foreach ($finals as $assessment) {
                foreach ($assessment->scores as $score) {
                    $indicator = $this->indicators()->firstWhere('id', $score->indicator_id);
                    if (! $indicator || $indicator->weightFor($component) <= 0) {
                        continue;
                    }
                    $parts = $this->indicatorParts($indicator, $component, $score->score, (float) $score->factor);
                    $code = $codeOf($indicator->clo_id);
                    $clos[$code]['base'] = ($clos[$code]['base'] ?? 0) + $parts['base'] / $finals->count();
                    $clos[$code]['total'] = ($clos[$code]['total'] ?? 0) + $parts['base'] * $parts['factor'] / $finals->count();
                }
            }
            foreach ($clos as $code => $cell) {
                $clos[$code]['lisan'] = $cell['base'] > 0 ? $cell['total'] / $cell['base'] : null;
            }
            $teamComponents[$component] = ['ready' => true, 'clos' => $clos, 'total' => array_sum(array_column($clos, 'total'))];
        }

        $peerComponents = $columns->filter(fn ($c) => $c['peer']);
        $peer = $peerComponents->isEmpty() ? collect() : PeerReview::query()->where('team_id', $team->id)->get();
        $members = $team->activeUsers;
        $expected = ($members->count() - 1) * count(config('capstone.peer_aspects'));

        return $members->map(function (User $student) use ($teamComponents, $peerComponents, $peer, $expected, $codeOf) {
            $components = $teamComponents;
            $received = $peer->where('reviewee_id', $student->id);
            $ready = $expected > 0 && $received->count() >= $expected;
            foreach ($peerComponents as $component => $meta) {
                $clos = [];
                if ($ready) {
                    $avg = (float) $received->avg('score');
                    foreach ($this->indicatorsFor($component) as $indicator) {
                        $code = $codeOf($indicator->clo_id);
                        $clos[$code]['average'] = $avg;
                        $clos[$code]['total'] = ($clos[$code]['total'] ?? 0) + ($avg / 100) * $indicator->weightFor($component);
                    }
                }
                $components[$component] = ['ready' => $ready, 'clos' => $clos, 'total' => array_sum(array_column($clos, 'total'))];
            }

            return ['student' => $student, 'components' => $components];
        });
    }

    /**
     * Raw Data per indikator untuk satu bentuk penilaian (kolom = indikator berbobot pada bentuk itu, urut kode).
     * Per mahasiswa per indikator: doc = nilai dokumen 0–100 (nilai level; peer review: rata-rata nilai 0–90),
     * level = tingkat rubrik (null bila beberapa penilai / peer review), oral = Faktor Pemahaman (null bila tanpa lisan),
     * value = nilai indikator (= doc ÷ 100 × bobot × lisan). ready = false bila belum final (peer review: belum
     * lengkap untuk mahasiswa ini). Penilaian dosen berlaku per tim, sehingga sama untuk seluruh anggota.
     *
     * @return Collection<int, array{student: User, ready: bool, cells: array<int, array{doc: float, level: ?int, oral: ?float, value: float}>, total: float}>
     */
    public function rawIndicatorsForTeam(Team $team, string $component): Collection
    {
        $team->loadMissing(['activeUsers', 'assessments.scores']);
        $indicators = $this->indicatorsFor($component);
        $members = $team->activeUsers;

        if (AssessmentComponent::isPeerKey($component)) {
            $reviews = PeerReview::query()->where('team_id', $team->id)->get();
            $expected = ($members->count() - 1) * count(config('capstone.peer_aspects'));

            return $members->map(function (User $student) use ($reviews, $expected, $indicators, $component) {
                $received = $reviews->where('reviewee_id', $student->id);
                $ready = $expected > 0 && $received->count() >= $expected;
                $avg = $ready ? (float) $received->avg('score') : 0.0;
                $cells = $ready ? $indicators->mapWithKeys(fn (Indicator $i) => [$i->id => [
                    'doc' => $avg, 'level' => null, 'oral' => null, 'value' => ($avg / 100) * $i->weightFor($component),
                ]])->all() : [];

                return ['student' => $student, 'ready' => $ready, 'cells' => $cells, 'total' => array_sum(array_column($cells, 'value'))];
            });
        }

        $finals = $this->finalsFor($team, $component);
        $ready = ! $finals->contains(null);
        $cells = [];
        if ($ready) {
            foreach ($indicators as $indicator) {
                $parts = $finals->map(function (Assessment $assessment) use ($indicator, $component) {
                    $score = $assessment->scores->firstWhere('indicator_id', $indicator->id);

                    return $score ? ['level' => (int) $score->score] + $this->indicatorParts($indicator, $component, $score->score, (float) $score->factor) : null;
                })->filter();
                if ($parts->isEmpty()) {
                    continue;
                }
                $cells[$indicator->id] = [
                    'doc' => (float) $parts->avg(fn (array $p) => $p['ratio'] * 100),
                    'level' => $parts->count() === 1 ? $parts->first()['level'] : null,
                    'oral' => $indicator->usesOralFactor($component) ? (float) $parts->avg('factor') : null,
                    'value' => (float) $parts->avg(fn (array $p) => $p['base'] * $p['factor']),
                ];
            }
        }
        $total = array_sum(array_column($cells, 'value'));

        return $members->map(fn (User $student) => ['student' => $student, 'ready' => $ready, 'cells' => $cells, 'total' => $total]);
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
                // Draft dosen belum dihitung sebagai dinilai: hanya Simpan Final (status final) yang "sudah dinilai".
                'status' => $complete ? 'final' : 'belum',
                'points' => round($complete ? $finals->avg(fn (Assessment $a) => $this->assessmentPoints($a, $component, $indicators)) : 0, 2),
                'max' => round($indicators->sum(fn (Indicator $i) => $i->weightFor($component)), 2),
            ];
        });
    }

    /**
     * Nilai per mahasiswa dalam satu tim. Bila semua komponen lengkap dan pembimbing mengisi Penyesuaian Nilai untuk
     * mahasiswa itu: total = nilai disesuaikan, system_total = nilai sistem, scale = disesuaikan ÷ sistem (pengali kolom
     * iGadis agar jumlahnya sama dengan nilai disesuaikan).
     *
     * @return array{rows: Collection, missing: list<string>, complete: bool}
     */
    public function forTeam(Team $team): array
    {
        $team->loadMissing(['activeUsers', 'assessments.scores', 'lecturers', 'scoreAdjustments']);
        $missing = [];

        $teamPoints = []; // clo_id => poin dari penilaian dosen
        $teamCells = [];  // bentuk penilaian => clo_id => poin (kolom ekspor iGadis)
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
                    $value = $this->indicatorValue($indicator, $component, $score->score, $score->factor) / $finals->count();
                    $teamPoints[$indicator->clo_id] = ($teamPoints[$indicator->clo_id] ?? 0) + $value;
                    $teamCells[$component][$indicator->clo_id] = ($teamCells[$component][$indicator->clo_id] ?? 0) + $value;
                }
            }
        }

        $peerIndicators = $this->indicatorsFor('peer_review');
        $peer = PeerReview::query()->where('team_id', $team->id)->get();
        $members = $team->activeUsers;

        $rows = $members->map(function (User $student) use ($teamPoints, $teamCells, $peerIndicators, $peer, $members, &$missing) {
            $points = $teamPoints;
            $cells = $teamCells;
            $received = $peer->where('reviewee_id', $student->id);
            $expected = ($members->count() - 1) * count(config('capstone.peer_aspects'));
            $peerComplete = $expected > 0 && $received->count() >= $expected;

            if ($peerIndicators->isNotEmpty()) {
                if ($peerComplete) {
                    $avg = (float) $received->avg('score');
                    foreach ($peerIndicators as $indicator) {
                        $value = ($avg / 100) * $indicator->weightFor('peer_review'); // nilai peer 0 / 50 / 65 / 75 / 90
                        $points[$indicator->clo_id] = ($points[$indicator->clo_id] ?? 0) + $value;
                        $cells['peer_review'][$indicator->clo_id] = ($cells['peer_review'][$indicator->clo_id] ?? 0) + $value;
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
                // Poin per bentuk penilaian per kode CLO (hanya bentuk penilaian yang sudah lengkap / peer review lengkap)
                'cells' => collect($cells)->map(fn (array $byClo) => collect($byClo)->mapWithKeys(
                    fn (float $value, int $cloId) => [$this->clos()->firstWhere('id', $cloId)?->code => round($value, 4)]
                )->all())->all(),
                'total' => $total,
                'grade' => $this->grade($total),
                'peer_complete' => $peerComplete,
            ];
        });

        $complete = $missing === [];
        $rows = $rows->map(function (array $row) use ($team, $complete) {
            $adjustment = $team->scoreAdjustments->firstWhere('student_id', $row['student']->id);
            $system = $row['total'];
            $adjusted = $complete && $adjustment ? round($adjustment->score, 2) : null;
            $total = $adjusted ?? $system;

            return array_merge($row, [
                'system_total' => $system,
                'adjusted' => $adjusted,
                'adjustment' => $adjustment,
                'total' => $total,
                'grade' => $this->grade($total),
                'scale' => $adjusted !== null && $system > 0 ? $adjusted / $system : 1.0,
            ]);
        });

        return ['rows' => $rows, 'missing' => $missing, 'complete' => $complete];
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
