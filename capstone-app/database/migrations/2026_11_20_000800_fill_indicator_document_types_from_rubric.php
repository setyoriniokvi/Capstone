<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Isi "Dokumen terkait" tiap indikator sesuai kolom Assessment sheet "Rubrikasi Dokumen v1" (Rubrik Capstone Design
 * Project.xlsx). Kolom itu = bentuk penilaian yang memberi bobot ke indikator (identik dengan bobot indikator di sistem),
 * lalu tiap bentuk penilaian diterjemahkan ke jenis dokumennya (Jadwal pengisian nilai dosen → Jenis dokumen):
 * Proposal → Proposal, Laporan Progress → Laporan Progress, Sidang Capstone → Laporan Akhir + Materi Presentasi,
 * Pameran → Poster, Peer Review → (tanpa dokumen). Berlaku untuk semua periode dan data master bawaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $order = array_keys(config('capstone.document_types'));
        foreach (DB::table('indicators')->get(['id', 'period_id']) as $indicator) {
            $components = DB::table('indicator_component_weights')->where('indicator_id', $indicator->id)->where('weight', '>', 0)->pluck('component');
            $types = DB::table('assessment_components')->whereIn('key', $components)
                ->when($indicator->period_id, fn ($q) => $q->where('period_id', $indicator->period_id), fn ($q) => $q->whereNull('period_id'))
                ->pluck('document_types')
                ->flatMap(fn ($json) => (array) json_decode((string) $json, true))
                ->unique();
            $sorted = array_values(array_intersect($order, $types->all()));
            DB::table('indicators')->where('id', $indicator->id)->update(['document_types' => $sorted ? json_encode($sorted) : null]);
        }
    }

    public function down(): void
    {
        // Data informasi; tidak dikembalikan.
    }
};
