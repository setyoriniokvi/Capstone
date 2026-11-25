<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * F13 — bentuk penilaian (master data). "roles" = siapa yang mengisi: pembimbing, penguji (boleh keduanya),
 * atau mahasiswa (peer review). Daftar aktif dicerminkan ke config('capstone.components') agar seluruh kode
 * membaca satu sumber: [key => ['label', 'role' (penilai pertama), 'roles']].
 */
class AssessmentComponent extends Model
{
    use BelongsToPeriod;

    public const LECTURER_ROLES = ['pembimbing' => 'Pembimbing', 'penguji' => 'Penguji'];

    /** Peran pengisi peer review. */
    public const PEER_ROLE = 'mahasiswa';

    protected $fillable = ['key', 'label', 'roles', 'document_types', 'formula', 'sort_order'];

    /**
     * Rumus nilai indikator bawaan: (Nilai level ÷ 100) × Bobot × Faktor Lisan × 1.
     * divisor: level = nilai tingkat Rubrik Dokumen ÷ 100 (config capstone.score_levels: 5 = 95, 4 = 80, 3 = 70, 2 = 50,
     * 1 = 40, 0 = 0) | max = interval ÷ interval maksimum indikator (banyaknya poin rubrik) | fixed = interval ÷ angka tetap ($fixed);
     * weight: kali bobot indikator; oral: kali faktor pemahaman (Rubrik Lisan untuk skema lisan, faktor bawaan untuk lainnya);
     * multiplier: pengali tambahan.
     */
    public const FORMULA = ['divisor' => 'level', 'fixed' => 5, 'weight' => true, 'oral' => true, 'multiplier' => 1];

    protected function casts(): array
    {
        return ['roles' => 'array', 'document_types' => 'array', 'formula' => 'array'];
    }

    /** Rumus lengkap (nilai kosong diisi bawaan). */
    public static function normalizeFormula(?array $formula): array
    {
        $f = array_merge(self::FORMULA, array_filter((array) $formula, fn ($v) => $v !== null));

        return [
            'divisor' => in_array($f['divisor'], ['level', 'max', 'fixed'], true) ? $f['divisor'] : 'level',
            'fixed' => max(1, (int) $f['fixed']),
            'weight' => (bool) $f['weight'],
            'oral' => (bool) $f['oral'],
            'multiplier' => (float) $f['multiplier'],
        ];
    }

    /** Rumus bentuk penilaian yang sedang berlaku (periode konteks). */
    public static function formulaOf(string $key): array
    {
        return self::normalizeFormula(config("capstone.components.{$key}.formula"));
    }

    /** Teks rumus, mis. "(Nilai level ÷ 100) × Bobot × Faktor Lisan". */
    public static function formulaText(array $formula): string
    {
        $f = self::normalizeFormula($formula);
        $num = fn (float $v) => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
        $parts = [match ($f['divisor']) {
            'level' => '(Nilai level ÷ 100)',
            'max' => '(Interval ÷ Interval maksimum)',
            default => '(Interval ÷ '.$f['fixed'].')',
        }];
        if ($f['weight']) {
            $parts[] = 'Bobot';
        }
        if ($f['oral']) {
            $parts[] = 'Faktor Lisan';
        }
        if (abs($f['multiplier'] - 1) > 0.0001) {
            $parts[] = $num($f['multiplier']);
        }

        return implode(' × ', $parts);
    }

    /** Nilai tingkat Rubrik Dokumen (0–100), mis. 5 → 95. */
    public static function levelValue(int $level): float
    {
        return (float) (config("capstone.score_levels.{$level}.value") ?? 0);
    }

    public function rolesLabel(): string
    {
        return collect($this->roles)->map(fn ($r) => self::LECTURER_ROLES[$r] ?? ucfirst($r))->implode(' & ');
    }

    /** Muat daftar bentuk penilaian dari database ke config; bawaan config dipakai bila tabel belum tersedia. */
    public static function syncConfig(): void
    {
        try {
            $rows = static::query()->orderBy('sort_order')->orderBy('id')->get()
                ->mapWithKeys(fn (self $c) => [$c->key => ['label' => $c->label, 'role' => $c->roles[0] ?? null, 'roles' => $c->roles,
                    'document_types' => $c->document_types ?? [], 'formula' => self::normalizeFormula($c->formula)]])
                ->all();
        } catch (Throwable) {
            $rows = [];
        }

        config(['capstone.components' => $rows ?: collect(config('capstone.default_components'))
            ->map(fn ($c) => $c + ['roles' => [$c['role']]])->all()]);
    }

    /**
     * Bentuk penilaian untuk tombol "Nilai" pada deliverable: yang memuat jenis dokumen $type (diatur admin di Jadwal
     * pengisian nilai dosen → Jenis dokumen) dan menjadi wewenang $role. Null bila tidak ada.
     */
    public static function forDocument(?string $type, string $role): ?string
    {
        if (! $type) {
            return null;
        }

        return collect(config('capstone.components'))
            ->filter(fn (array $c) => in_array($type, $c['document_types'] ?? [], true) && in_array($role, $c['roles'] ?? [], true))
            ->keys()->first();
    }

    /** Apakah ada bentuk penilaian (peran apa pun) yang menilai jenis dokumen ini. */
    public static function hasDocument(?string $type): bool
    {
        return $type && collect(config('capstone.components'))->contains(fn (array $c) => in_array($type, $c['document_types'] ?? [], true));
    }

    /** @return list<string> penilai bentuk penilaian (pembimbing / penguji / mahasiswa) */
    public static function rolesOf(string $key): array
    {
        return config("capstone.components.{$key}.roles", []);
    }

    /** Peer review = bentuk penilaian yang diisi mahasiswa (bukan dosen). */
    public function isPeerReview(): bool
    {
        return in_array(self::PEER_ROLE, (array) $this->roles, true);
    }

    /** Sama dengan isPeerReview(), dari kunci bentuk penilaian pada config periode konteks. */
    public static function isPeerKey(string $key): bool
    {
        return in_array(self::PEER_ROLE, self::rolesOf($key), true);
    }

    /**
     * Bentuk penilaian yang dinilai dosen (tanpa peer review) dari config periode konteks.
     *
     * @return array<string, array> kunci => ['label', 'role', 'roles', 'document_types', 'formula']
     */
    public static function lecturerConfig(): array
    {
        return collect(config('capstone.components'))
            ->reject(fn (array $c) => in_array(self::PEER_ROLE, $c['roles'] ?? [], true))
            ->all();
    }

    /** Penilai dosen terpilih dengan urutan tetap (pembimbing lalu penguji); nilai lain dibuang. */
    public static function orderedLecturerRoles(array $roles): array
    {
        return array_values(array_intersect(array_keys(self::LECTURER_ROLES), $roles));
    }

    /** Kunci baru yang belum dipakai pada periode konteks, dari nama bentuk penilaian (mis. "Sidang Akhir" → sidang_akhir). */
    public static function uniqueKey(string $label): string
    {
        $base = Str::limit(Str::slug($label, '_'), 24, '') ?: 'penilaian';
        $key = $base;
        for ($i = 2; static::query()->where('key', $key)->exists(); $i++) {
            $key = "{$base}_{$i}";
        }

        return $key;
    }

    /**
     * Bentuk penilaian dosen baru di periode konteks (urutan paling akhir).
     *
     * @param  array{label: string, roles: array, document_types?: array}  $data
     */
    public static function createForLecturers(array $data): self
    {
        return static::query()->create([
            'key' => self::uniqueKey($data['label']),
            'label' => $data['label'],
            'roles' => self::orderedLecturerRoles($data['roles']),
            'document_types' => array_values($data['document_types'] ?? []),
            'sort_order' => (int) static::query()->max('sort_order') + 1,
        ]);
    }

    /** Sudah ada penilaian dosen untuk bentuk penilaian ini pada periodenya (bentuk penilaian tidak boleh dihapus lagi). */
    public function hasAssessments(): bool
    {
        return Assessment::query()->where('component', $this->key)
            ->whereHas('team', fn ($q) => $q->where('period_id', $this->period_id))->exists();
    }

    /**
     * Hapus bentuk penilaian beserta jadwal pengisian dan bobotnya di CLO & indikator — hanya milik periodenya sendiri
     * (kunci bentuk penilaian yang sama dipakai juga oleh periode lain dan data master bawaan).
     */
    public function deleteWithWeights(): void
    {
        AssessmentWindow::query()->where('component', $this->key)->where('period_id', $this->period_id)->delete();
        IndicatorComponentWeight::query()->where('component', $this->key)
            ->whereIn('indicator_id', Indicator::inPeriod($this->period_id)->select('id'))->delete();
        CloComponentWeight::query()->where('component', $this->key)
            ->whereIn('clo_id', Clo::inPeriod($this->period_id)->select('id'))->delete();
        $this->delete();
    }
}
