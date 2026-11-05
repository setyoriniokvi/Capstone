<?php

namespace App\Services;

use App\Models\LoadQuota;
use App\Models\Period;
use App\Models\TeamLecturer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * F9 / F10 — kuota beban dosen dan dashboard load balancing.
 * Maksimum = kuota tim yang dapat dibimbing / diuji (0 = tidak menerima tim); sisa kuota di plotting dihitung dari
 * maksimum. Minimum hanya untuk menandai dosen UNDER. Prioritas: per dosen → global.
 */
class LoadBalanceService
{
    private ?Collection $quotas = null;

    /** @return array{pembimbing: array{min: int, max: int}, penguji: array{min: int, max: int}, source: string} */
    public function quotaFor(User $lecturer, Period $period): array
    {
        $quotas = $this->quotas ??= LoadQuota::query()->where('period_id', $period->id)->get();
        $quota = $quotas->first(fn ($q) => $q->scope === 'dosen' && $q->lecturer_id === $lecturer->id)
            ?? $quotas->firstWhere('scope', 'global');

        return [
            'pembimbing' => ['min' => (int) $quota?->supervise_min, 'max' => (int) $quota?->supervise_max],
            'penguji' => ['min' => (int) $quota?->examine_min, 'max' => (int) $quota?->examine_max],
            'source' => $quota?->scope ?? 'belum diatur',
        ];
    }

    /**
     * @return Collection<int, array{lecturer: User, counts: array, quota: array, remaining: array, flags: array}>
     */
    public function rows(Period $period): Collection
    {
        $counts = TeamLecturer::query()
            ->whereHas('team', fn ($q) => $q->where('period_id', $period->id))
            ->selectRaw('lecturer_id, role, count(*) as total')
            ->groupBy('lecturer_id', 'role')
            ->get()
            ->groupBy('lecturer_id');

        return User::query()->lecturers()->where('is_active', true)
            ->with('lecturerProfile')
            ->orderBy('name')
            ->get()
            ->map(function (User $lecturer) use ($counts, $period) {
                $own = $counts->get($lecturer->id, collect());
                $c = [
                    'pembimbing' => (int) $own->firstWhere('role', 'pembimbing')?->total,
                    'penguji' => (int) $own->firstWhere('role', 'penguji')?->total,
                ];
                $quota = $this->quotaFor($lecturer, $period);

                return [
                    'lecturer' => $lecturer,
                    'counts' => $c,
                    'quota' => $quota,
                    'remaining' => [
                        'pembimbing' => $this->remaining($c['pembimbing'], $quota['pembimbing']),
                        'penguji' => $this->remaining($c['penguji'], $quota['penguji']),
                    ],
                    'flags' => [
                        'pembimbing' => $this->flag($c['pembimbing'], $quota['pembimbing']),
                        'penguji' => $this->flag($c['penguji'], $quota['penguji']),
                    ],
                ];
            });
    }

    /** Sisa kuota (dari maksimum): berkurang setiap tim yang sudah diplot; tidak pernah negatif. */
    public function remaining(int $count, array $quota): int
    {
        return max(0, $quota['max'] - $count);
    }

    /** OVER (melebihi maksimum) / UNDER (di bawah minimum) / OK (Alur §3.10). Informatif, tidak memblokir plotting. */
    public function flag(int $count, array $quota): string
    {
        return match (true) {
            $count > $quota['max'] => 'OVER',
            $count < $quota['min'] => 'UNDER',
            default => 'OK',
        };
    }

    /** F10 — hijau (tersedia) bila masih ada sisa kuota, merah (penuh) bila habis; maksimum 0 = tidak menerima tim. */
    public function isAvailable(int $count, array $quota): bool
    {
        return $count < $quota['max'];
    }
}
