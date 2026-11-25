<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rubrik dokumen boleh memiliki kurang dari 5 poin: tingkat 2–5 boleh kosong (dari atas). Banyaknya poin yang terisi
 * menjadi interval maksimum penilaian indikator (Indicator::maxInterval).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_rubrics', function (Blueprint $table) {
            foreach ([2, 3, 4, 5] as $level) {
                $table->text("level_{$level}")->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_rubrics', function (Blueprint $table) {
            foreach ([2, 3, 4, 5] as $level) {
                $table->text("level_{$level}")->nullable(false)->default('')->change();
            }
        });
    }
};
