<?php

namespace App\Services;

use App\Models\RegistrationRule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * F6 — screening otomatis registrasi tim berdasarkan aturan F5.
 * Menghasilkan daftar flag per syarat dan skor kelengkapan (0–100).
 */
class ScreeningService
{
    /**
     * @return array{checks: list<array{key: string, label: string, passed: bool, detail: string}>, score: int, passed: bool}
     */
    public function evaluate(Team $team): array
    {
        $team->loadMissing('members.user', 'period.registrationRule.courses');
        $members = $team->members->where('invite_status', 'diterima');
        $teamRule = $team->period->registrationRule;

        $min = $teamRule?->min_members ?? config('capstone.team_size.min');
        $max = $teamRule?->max_members ?? config('capstone.team_size.max');

        $checks = [];
        $checks[] = $this->check(
            'jumlah_anggota',
            "Jumlah anggota {$min}–{$max} orang",
            $members->count() >= $min && $members->count() <= $max,
            $members->count().' anggota terkonfirmasi'
        );

        foreach ($members as $member) {
            $checks = array_merge($checks, $this->memberChecks($member->user, $teamRule, $member));
        }

        if ($teamRule?->require_distinct_region ?? true) {
            $regions = $this->distinct($members->pluck('user.origin_region'));
            $checks[] = $this->check(
                'daerah_asal',
                'Minimal satu anggota berbeda daerah asal',
                $regions->count() >= 2,
                $regions->isEmpty() ? 'Daerah asal belum diisi' : $regions->implode(', ')
            );
        }

        if ($teamRule?->require_distinct_specialization ?? true) {
            $specs = $this->distinct($members->pluck('user.specialization'));
            $checks[] = $this->check(
                'peminatan',
                'Minimal satu anggota berbeda peminatan',
                $specs->count() >= 2,
                $specs->isEmpty() ? 'Peminatan belum diisi' : $specs->implode(', ')
            );
        }

        $passed = collect($checks)->where('passed', true)->count();
        $score = $checks === [] ? 0 : (int) round($passed / count($checks) * 100);

        return ['checks' => $checks, 'score' => $score, 'passed' => $passed === count($checks)];
    }

    public function apply(Team $team): array
    {
        $result = $this->evaluate($team);
        $team->forceFill(['screening' => $result['checks'], 'screening_score' => $result['score']])->save();

        return $result;
    }

    /** Checklist kelayakan individu (F19), juga dipakai di screening tim. */
    public function eligibility(User $student, ?RegistrationRule $rule): array
    {
        $checks = [];

        if ($student->sks_passed === null) {
            $checks[] = $this->check('sks', 'SKS lulus', false, 'Data SKS belum tersedia — hubungi admin');
        } else {
            $minSks = $rule?->min_sks ?? 0;
            $checks[] = $this->check('sks', "SKS lulus minimal {$minSks}", $student->sks_passed >= $minSks, "{$student->sks_passed} SKS");
        }

        $passedCourses = collect($student->passed_courses ?? [])->map(fn ($c) => mb_strtoupper(trim((string) $c)));
        foreach ($rule?->courses ?? [] as $course) {
            $checks[] = $this->check(
                'mk_'.$course->code,
                "Lulus {$course->code} — {$course->name}",
                $passedCourses->contains(mb_strtoupper($course->code)),
                $passedCourses->contains(mb_strtoupper($course->code)) ? 'Lulus' : 'Belum tercatat lulus'
            );
        }

        return $checks;
    }

    private function memberChecks(User $user, ?RegistrationRule $rule, $member): array
    {
        $checks = array_map(function (array $c) use ($user) {
            $c['key'] = $user->nim.'_'.$c['key'];
            $c['label'] = $user->name.' · '.$c['label'];

            return $c;
        }, $this->eligibility($user, $rule));

        $checks[] = $this->check(
            $user->nim.'_lampiran',
            $user->name.' · Lampiran KHS',
            filled($member->khs_path),
            filled($member->khs_path) ? 'Lengkap' : 'Belum diunggah'
        );

        return $checks;
    }

    /**
     * Ringkasan screening per syarat tingkat tim untuk halaman Tim Saya: SKS, prasyarat mata kuliah,
     * multikultural, multidisiplin — mengikuti aturan registrasi prodi tim pada periode (F5).
     *
     * @return array{prodi: ?string, checks: list<array{label: string, passed: bool, detail: string}>}
     */
    public function summary(Team $team): array
    {
        $team->loadMissing('members.user', 'period.registrationRule.courses');
        $members = $team->members->where('invite_status', 'diterima')->values();
        $teamRule = $team->period->registrationRule;
        $total = $members->count();

        $results = $members->map(fn ($m) => collect($this->eligibility($m->user, $teamRule)));
        $sksOk = $results->filter(fn ($r) => $r->firstWhere('key', 'sks')['passed'] ?? false)->count();
        $courseOk = $results->filter(fn ($r) => $r->reject(fn ($c) => $c['key'] === 'sks')->every('passed'))->count();
        $courses = $teamRule?->courses ?? collect();

        $checks = [
            [
                'label' => 'SKS minimal '.($teamRule?->min_sks ?? 0),
                'passed' => $total > 0 && $sksOk === $total,
                'detail' => $sksOk === $total ? 'Semua anggota memenuhi' : "{$sksOk}/{$total} anggota memenuhi",
            ],
            [
                'label' => $courses->isEmpty() ? 'Prasyarat mata kuliah' : 'Prasyarat & micro-course',
                'passed' => $total > 0 && $courseOk === $total,
                'detail' => $courses->isEmpty() ? 'Tidak ada prasyarat mata kuliah' : "{$courseOk}/{$total} anggota lulus",
            ],
        ];

        if ($teamRule?->require_distinct_region ?? true) {
            $regions = $members->pluck('user.origin_region')->filter()->unique(fn ($v) => mb_strtolower(trim($v)))->map(fn ($v) => trim($v));
            $checks[] = [
                'label' => 'Multikultural (≥ 1 beda daerah asal)',
                'passed' => $regions->count() >= 2,
                'detail' => $regions->isEmpty() ? 'Daerah asal belum diisi' : $regions->map(fn ($r) => trim(Str::afterLast($r, ',')))->unique()->implode(', '),
            ];
        }

        if ($teamRule?->require_distinct_specialization ?? true) {
            $specs = $this->distinct($members->pluck('user.specialization'));
            $checks[] = [
                'label' => 'Multidisiplin (≥ 1 beda peminatan)',
                'passed' => $specs->count() >= 2,
                'detail' => $specs->isEmpty() ? 'Peminatan belum diisi' : $specs->count().' peminatan berbeda',
            ];
        }

        return ['prodi' => $team->prodi_code, 'checks' => $checks];
    }

    private function distinct(Collection $values): Collection
    {
        return $values->filter()->map(fn ($v) => mb_strtolower(trim($v)))->unique()->values();
    }

    private function check(string $key, string $label, bool $passed, string $detail): array
    {
        return compact('key', 'label', 'passed', 'detail');
    }
}
