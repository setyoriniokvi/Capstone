<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F15 — kolom "Contoh Pemenuhan Tingkat 5" pada rubrik dokumen tidak dipakai lagi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_rubrics', function (Blueprint $table) {
            $table->dropColumn('example_level_5');
        });
    }

    public function down(): void
    {
        Schema::table('document_rubrics', function (Blueprint $table) {
            $table->text('example_level_5')->nullable();
        });
    }
};
