<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F47 — status penilaian mengikuti skor (real time): "final" (lengkap) hanya bila semua indikator berbobot pada bentuk
 * penilaiannya sudah dinilai, selain itu "draft". Menyamakan penilaian lama — mis. yang skornya ikut terhapus saat
 * master penilaian disamakan dengan berkas rubrik — agar tidak tampil "Lengkap" tanpa nilai.
 */
return new class extends Migration
{
    public function up(): void
    {
        $required = DB::table('indicator_component_weights')->where('weight', '>', 0)
            ->get(['indicator_id', 'component'])->groupBy('component')->map(fn ($rows) => $rows->pluck('indicator_id'));

        foreach (DB::table('assessments')->get(['id', 'component', 'status', 'submitted_at']) as $assessment) {
            $needed = $required->get($assessment->component, collect());
            $scored = DB::table('assessment_scores')->where('assessment_id', $assessment->id)->pluck('indicator_id');
            $complete = $needed->isNotEmpty() && $needed->diff($scored)->isEmpty();

            DB::table('assessments')->where('id', $assessment->id)->update([
                'status' => $complete ? 'final' : 'draft',
                'submitted_at' => $complete ? ($assessment->submitted_at ?? now()) : null,
            ]);
        }
    }

    /** Status hasil hitung ulang tidak dikembalikan. */
    public function down(): void {}
};
