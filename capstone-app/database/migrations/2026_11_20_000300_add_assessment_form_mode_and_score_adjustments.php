<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - periods.assessment_form_mode: tampilan form penilaian dosen per periode (diatur admin di halaman Indikator):
 *   split (skenario 2, bawaan) = kolom Dokumen & Lisan berdampingan, masing-masing dengan centang sendiri;
 *   sequential (skenario 1) = satu centang untuk interval + lisan, CLO berikutnya terbuka setelah CLO sebelumnya selesai.
 * - score_adjustments: Penyesuaian Nilai oleh dosen pembimbing — nilai akhir spesifik per mahasiswa (menggantikan nilai
 *   akhir sistem; kolom iGadis diskalakan proporsional), dengan alasan. Tidak terlihat mahasiswa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->string('assessment_form_mode', 20)->default('split');
        });

        Schema::create('score_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lecturer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('score', 5, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('score_adjustments');
        Schema::table('periods', function (Blueprint $table) {
            $table->dropColumn('assessment_form_mode');
        });
    }
};
