<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Urutan bentuk penilaian mengikuti template iGadis (tab "Indikator Draft -> Igadis"): Proposal · Laporan Progress ·
 * Sidang Capstone (Pembimbing) · Sidang Capstone (Penguji) · Peer Review · Pameran. Peer Review dipindah sebelum Pameran
 * (semua periode + data master bawaan). Bentuk penilaian lain tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->swap(fn ($peer, $pameran) => $peer > $pameran);
    }

    public function down(): void
    {
        $this->swap(fn ($peer, $pameran) => $peer < $pameran);
    }

    private function swap(callable $needed): void
    {
        foreach (DB::table('assessment_components')->select('period_id')->distinct()->pluck('period_id') as $periodId) {
            $scope = fn () => DB::table('assessment_components')->when($periodId, fn ($q) => $q->where('period_id', $periodId), fn ($q) => $q->whereNull('period_id'));
            $peer = $scope()->where('key', 'peer_review')->first();
            $pameran = $scope()->where('key', 'pameran')->first();
            if ($peer && $pameran && $needed($peer->sort_order, $pameran->sort_order)) {
                DB::table('assessment_components')->where('id', $peer->id)->update(['sort_order' => $pameran->sort_order]);
                DB::table('assessment_components')->where('id', $pameran->id)->update(['sort_order' => $peer->sort_order]);
            }
        }
    }
};
