<?php

use Database\Seeders\AssessmentMasterSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F13–F16 — poin penilaian rubrik dokumen menjadi opsional, lalu master penilaian disamakan dengan
 * "Rubrik Capstone Design Project.xlsx" (8 CLO, 30 indikator, rubrik dokumen, 5 level rubrik lisan).
 * Indikator contoh lama (IND-1 … IND-8) dihapus beserta skor penilaian yang memakainya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_rubrics', function (Blueprint $table) {
            $table->text('assessment_points')->nullable()->change();
        });

        (new AssessmentMasterSeeder)->run();
    }

    /** Isi master data tidak dikembalikan; hanya kolom poin penilaian yang kembali wajib. */
    public function down(): void
    {
        Schema::table('document_rubrics', function (Blueprint $table) {
            $table->text('assessment_points')->nullable(false)->change();
        });
    }
};
