<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dokumen terkait (Rubrik Dokumen) hanya untuk dokumen yang benar-benar dinilai: Materi Presentasi dan Poster dihapus
 * (sidang menilai Laporan Akhir; pameran dan peer review tanpa dokumen terkait). Semua periode + data master bawaan.
 */
return new class extends Migration
{
    private const UNASSESSED = ['materi_presentasi', 'poster'];

    public function up(): void
    {
        foreach (DB::table('indicators')->whereNotNull('document_types')->get(['id', 'document_types']) as $row) {
            $types = array_values(array_diff((array) json_decode((string) $row->document_types, true), self::UNASSESSED));
            DB::table('indicators')->where('id', $row->id)->update(['document_types' => $types ? json_encode($types) : null]);
        }
    }

    public function down(): void
    {
        // Data informasi; tidak dikembalikan.
    }
};
