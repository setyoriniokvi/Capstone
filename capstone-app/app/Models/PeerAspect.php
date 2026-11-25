<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * F28 — satu aspek rubrik peer review: nama, definisi, dan deskripsi 5 level (diatur admin di Rubrik Peer Review).
 * Level 1–5 bernilai 0 / 50 / 65 / 75 / 90 (POINTS); nilai inilah yang disimpan di peer_reviews.score.
 * Dimuat ke config('capstone.peer_aspects') & config('peer_rubric') saat aplikasi berjalan, sehingga formulir peer
 * review mahasiswa, rekap dosen, dan perhitungan nilai memakai rubrik yang sama.
 */
class PeerAspect extends Model
{
    use BelongsToPeriod;

    public const LEVELS = [1, 2, 3, 4, 5];

    /** Nilai tiap level (level => nilai). Skor peer review disimpan dalam nilai ini; dihitung (rata-rata ÷ 100) × bobot. */
    public const POINTS = [1 => 0, 2 => 50, 3 => 65, 4 => 75, 5 => 90];

    protected $fillable = ['key', 'name', 'definition', 'level_1', 'level_2', 'level_3', 'level_4', 'level_5', 'sort_order'];

    /** Isi bawaan config (dipakai bila periode konteks belum punya rubrik peer review). */
    private static ?array $defaults = null;

    /** Muat rubrik periode konteks dari database ke config; bawaan config dipakai bila tabel belum tersedia / kosong. */
    public static function syncConfig(): void
    {
        self::$defaults ??= ['capstone.peer_aspects' => config('capstone.peer_aspects'), 'peer_rubric' => config('peer_rubric')];
        try {
            $rows = static::query()->orderBy('sort_order')->orderBy('id')->get();
        } catch (Throwable) {
            $rows = collect();
        }
        if ($rows->isEmpty()) {
            config(self::$defaults);

            return;
        }

        config([
            'capstone.peer_aspects' => $rows->mapWithKeys(fn (self $a) => [$a->key => $a->name])->all(),
            'peer_rubric' => $rows->mapWithKeys(fn (self $a) => [$a->key => [
                'definition' => $a->definition,
                'levels' => collect(self::LEVELS)->mapWithKeys(fn ($l) => [$l => $a->{"level_{$l}"}])->all(),
            ]])->all(),
        ]);
    }

    /** Kunci unik dari nama aspek (mis. "Kemampuan presentasi" → "kemampuan_presentasi"), maks. 30 karakter. */
    public static function uniqueKey(string $name): string
    {
        $base = Str::limit(Str::slug($name, '_'), 24, '') ?: 'aspek';
        $key = $base;
        for ($i = 2; static::query()->where('key', $key)->exists(); $i++) {
            $key = "{$base}_{$i}";
        }

        return $key;
    }
}
