<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Dokumen terkait" indikator (Rubrik Dokumen) boleh lebih dari satu jenis dokumen: kolom document_type (satu) diganti
 * document_types (JSON daftar). Hanya informasi; tidak dipakai perhitungan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            $table->json('document_types')->nullable();
        });
        foreach (DB::table('indicators')->whereNotNull('document_type')->get(['id', 'document_type']) as $row) {
            DB::table('indicators')->where('id', $row->id)->update(['document_types' => json_encode([$row->document_type])]);
        }
        Schema::table('indicators', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }

    public function down(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            $table->string('document_type', 30)->nullable();
        });
        foreach (DB::table('indicators')->whereNotNull('document_types')->get(['id', 'document_types']) as $row) {
            DB::table('indicators')->where('id', $row->id)->update(['document_type' => json_decode($row->document_types, true)[0] ?? null]);
        }
        Schema::table('indicators', function (Blueprint $table) {
            $table->dropColumn('document_types');
        });
    }
};
