<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * F14 — baris informasi "Cara nilai dihitung" (rumus, skor, faktor pemahaman) dari berkas rubrik, ditampilkan di
 * halaman Indikator (hanya dibaca). Baris oral_factor menyimpan aturan yang dipakai perhitungan: skema yang memakai
 * faktor pemahaman dari Rubrik Lisan (settings.schemes) dan faktor untuk indikator lain (settings.default_factor).
 */
class ScoreRule extends Model
{
    protected $fillable = ['key', 'label', 'description', 'settings', 'sort_order'];

    /** Pengaturan faktor pemahaman yang sedang berlaku (dibaca sekali per proses). */
    private static ?array $oral = null;

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::$oral = null);
        static::deleted(fn () => self::$oral = null);
    }

    /** @return array{schemes: list<string>, default_factor: float} */
    public static function oralSettings(): array
    {
        if (self::$oral === null) {
            try {
                $settings = static::query()->where('key', 'oral_factor')->value('settings');
                $settings = is_string($settings) ? json_decode($settings, true) : $settings;
            } catch (Throwable) {
                $settings = null;
            }
            // Bawaan bila tabel belum ada: skema yang ditandai "oral" di config, faktor lain 1,00.
            self::$oral = [
                'schemes' => $settings['schemes'] ?? array_keys(array_filter(config('capstone.schemes'), fn ($s) => $s['oral'] ?? false)),
                'default_factor' => (float) ($settings['default_factor'] ?? 1),
            ];
        }

        return self::$oral;
    }

    /** Skema yang nilai indikatornya dikalikan faktor pemahaman dari Rubrik Lisan. */
    public static function oralSchemes(): array
    {
        return self::oralSettings()['schemes'];
    }

    /** Faktor untuk indikator tanpa verifikasi lisan. */
    public static function defaultFactor(): float
    {
        return self::oralSettings()['default_factor'];
    }

    /** Teks rumus yang sedang dipakai sistem (ditampilkan di kartu). */
    public static function formulaText(): string
    {
        return 'Nilai indikator = (Skor ÷ 5) × Bobot × Faktor Pemahaman';
    }
}
