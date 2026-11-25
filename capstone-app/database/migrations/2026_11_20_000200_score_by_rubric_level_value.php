<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nilai indikator memakai nilai tiap tingkat Rubrik Dokumen (5 = 95, 4 = 80, 3 = 70, 2 = 50, 1 = 40, 0 = 0):
 *   Nilai indikator = (Nilai level ÷ 100) × Bobot × Faktor Pemahaman.
 * Rumus bentuk penilaian yang memakai pembagi "Interval maksimum" (bawaan) diganti ke pembagi "Nilai level";
 * teks baris Rumus / Skor di kartu "Cara nilai dihitung" ikut diperbarui.
 */
return new class extends Migration
{
    private const FORMULA = 'Nilai indikator = (Nilai level / 100) x Bobot x Faktor Pemahaman';

    private const SCORE = 'Tingkat 0-5 dari Rubrik Dokumen, masing-masing bernilai: 5 Sangat Baik = 95, 4 Baik = 80, 3 Cukup = 70, 2 Kurang = 50, 1 Sangat Kurang = 40, 0 Tidak Mengumpulkan = 0. Indikator dinilai per tim; perbedaan kontribusi anggota diatur lewat Penyesuaian Nilai oleh dosen pembimbing.';

    public function up(): void
    {
        foreach (DB::table('assessment_components')->get() as $component) {
            $formula = json_decode((string) $component->formula, true) ?: [];
            if (($formula['divisor'] ?? 'max') === 'max') {
                $formula['divisor'] = 'level';
                DB::table('assessment_components')->where('id', $component->id)->update(['formula' => json_encode($formula)]);
            }
        }
        DB::table('score_rules')->where('key', 'formula')->update(['description' => self::FORMULA]);
        DB::table('score_rules')->where('key', 'score')->update(['description' => self::SCORE]);
    }

    public function down(): void
    {
        foreach (DB::table('assessment_components')->get() as $component) {
            $formula = json_decode((string) $component->formula, true) ?: [];
            if (($formula['divisor'] ?? null) === 'level') {
                $formula['divisor'] = 'max';
                DB::table('assessment_components')->where('id', $component->id)->update(['formula' => json_encode($formula)]);
            }
        }
        DB::table('score_rules')->where('key', 'formula')->update(['description' => 'Nilai indikator = (Skor / 5) x Bobot x Faktor Pemahaman']);
    }
};
