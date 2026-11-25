<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis dokumen yang dinilai pada tiap bentuk penilaian (boleh lebih dari satu). Menggantikan pemetaan hardcode
 * tombol "Nilai" di Deliverable (detail tim dosen): milestone dokumen berjenis X → bentuk penilaian yang memuat X dan
 * menjadi wewenang peran dosen. Baris lama diisi dengan pasangan yang selama ini berlaku.
 */
return new class extends Migration
{
    private const DEFAULTS = [
        'proposal' => ['proposal'],
        'laporan_progress' => ['laporan_progress'],
        'sidang_pembimbing' => ['laporan_akhir', 'materi_presentasi'],
        'sidang_penguji' => ['laporan_akhir', 'materi_presentasi'],
        'pameran' => ['poster'],
    ];

    public function up(): void
    {
        Schema::table('assessment_components', function (Blueprint $table) {
            $table->json('document_types')->nullable()->after('roles');
        });

        foreach (self::DEFAULTS as $key => $types) {
            DB::table('assessment_components')->where('key', $key)->update(['document_types' => json_encode($types)]);
        }
    }

    public function down(): void
    {
        Schema::table('assessment_components', function (Blueprint $table) {
            $table->dropColumn('document_types');
        });
    }
};
