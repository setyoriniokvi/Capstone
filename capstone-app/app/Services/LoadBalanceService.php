<?php

namespace App\Services;

use App\Models\LoadQuota;
use App\Models\Period;
use App\Models\TeamLecturer;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * F9 / F10 — kuota beban dosen dan dashboard load balancing.
 * Prioritas kuota: per dosen → kategori dosen → global.
 */
class LoadBalanceService
{
    private ?Collection $quotas = null;

    public function quotaFor(User $lecturer, Period $period): array
    {
        $quotas = $this->quotas ??= LoadQuota::query()->where('period_id', $period->id)->get();
        $category = $lecturer->lecturerProfile?->category();

        $quota = $quotas->first(fn ($q) => $q->scope === 'dosen' && $q->lecturer_id === $lecturer->id)
            ?? $quotas->first(fn ($q) => $q->scope === 'kategori' && $q->category === $category)
            ?? $quotas->firstWhere('scope', 'global');

        $values = [
            'pembimbing' => ['default' => (int) $quota?->supervise_default, 'min' => (int) $quota?->supervise_min, 'max' => (int) $quota?->supervise_max],
            'penguji' => ['default' => (int) $quota?->examine_default, 'min' => (int) $quota?->examine_min, 'max' => (int) $quota?->examine_max],
        ];
        $values['total'] = [
            'default' => $values['pembimbing']['default'] + $values['penguji']['default'],
            'min' => $values['pembimbing']['min'] + $values['penguji']['min'],
            'max' => $values['pembimbing']['max'] + $values['penguji']['max'],
        ];
        $values['source'] = $quota?->scope ?? 'belum diatur';

        return $values;
    }

    /**
     * @return Collection<int, array{lecturer: User, counts: array, quota: array, flags: array}>
     */
    public function rows(Period $period, ?string $prodi = null): Collection
    {
        $counts = TeamLecturer::query()
            ->whereHas('team', fn ($q) => $q->where('period_id', $period->id))
            ->selectRaw('lecturer_id, role, count(*) as total')
            ->groupBy('lecturer_id', 'role')
            ->get()
            ->groupBy('lecturer_id');

        return User::query()->lecturers()->where('is_active', true)
            ->with('lecturerProfile')
            ->when($prodi, fn ($q) => $q->where('prodi_code', $prodi))
            ->whereHas('lecturerProfile', fn ($q) => $q->where('can_supervise', true)->orWhere('can_examine', true))
            ->orderBy('name')
            ->get()
            ->map(function (User $lecturer) use ($counts, $period) {
                $own = $counts->get($lecturer->id, collect());
                $c = [
                    'pembimbing' => (int) $own->firstWhere('role', 'pembimbing')?->total,
                    'penguji' => (int) $own->firstWhere('role', 'penguji')?->total,
                ];
                $c['total'] = $c['pembimbing'] + $c['penguji'];
                $quota = $this->quotaFor($lecturer, $period);

                return [
                    'lecturer' => $lecturer,
                    'counts' => $c,
                    'quota' => $quota,
                    'flags' => [
                        'pembimbing' => $this->flag($c['pembimbing'], $quota['pembimbing']),
                        'penguji' => $this->flag($c['penguji'], $quota['penguji']),
                        'total' => $this->flag($c['total'], $quota['total']),
                    ],
                ];
            });
    }

    /** Status OVER / UNDER / OK (Alur §3.10). Flag bersifat informatif, tidak memblokir plotting. */
    public function flag(int $count, array $quota): string
    {
        return match (true) {
            $quota['max'] > 0 && $count > $quota['max'] => 'OVER',
            $count < $quota['min'] => 'UNDER',
            default => 'OK',
        };
    }

    /** F10 — merah (penuh) bila jumlah sudah mencapai maksimum, hijau (tersedia) bila belum. */
    public function isAvailable(int $count, array $quota): bool
    {
        return $quota['max'] === 0 || $count < $quota['max'];
    }
}
