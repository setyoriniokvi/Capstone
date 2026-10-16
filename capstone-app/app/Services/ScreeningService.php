<?php

namespace App\Services;

use App\Models\RegistrationRule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

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
        $team->loadMissing('members.user', 'period.registrationRules.courses');
        $members = $team->members->where('invite_status', 'diterima');
        $rules = $team->period->registrationRules->keyBy('prodi_code');
        $teamRule = $rules->get($team->prodi_code);

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
            $checks = array_merge($checks, $this->memberChecks($member->user, $rules->get($member->user->prodi_code), $member));
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
            $user->name.' · Lampiran KHS & domisili',
            filled($member->khs_path) && filled($member->domicile_path),
            filled($member->khs_path) && filled($member->domicile_path) ? 'Lengkap' : 'Belum diunggah'
        );

        return $checks;
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
