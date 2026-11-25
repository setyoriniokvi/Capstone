<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Skema penilaian "Observasi" dan "Peer + Observasi" tidak dipakai di Capstone: indikator berskema Observasi menjadi
 * Dokumen (sama-sama tanpa Faktor Lisan, nilai tidak berubah); Peer + Observasi menjadi Peer Review. Semua periode + bawaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('indicators')->where('scheme', 'observasi')->update(['scheme' => 'dokumen']);
        DB::table('indicators')->where('scheme', 'peer_observasi')->update(['scheme' => 'peer']);
    }

    public function down(): void
    {
        DB::table('indicators')->where('scheme', 'peer')->update(['scheme' => 'peer_observasi']);
    }
};
