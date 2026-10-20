<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Objek Capstone (instansi/organisasi/perusahaan) pada topik, kompetensi yang diharapkan & program studi
     * pada topik dosen, dan tautan portofolio (Google Drive) pada pengajuan topik dosen.
     */
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->string('object', 255)->nullable()->after('field');
            $table->text('competencies')->nullable()->after('object');
            $table->string('prodi_code', 10)->nullable()->after('competencies');
        });
        Schema::table('topic_applications', function (Blueprint $table) {
            $table->string('portfolio_url', 500)->nullable()->after('motivation');
        });
    }

    public function down(): void
    {
        Schema::table('topic_applications', fn (Blueprint $table) => $table->dropColumn('portfolio_url'));
        Schema::table('topics', fn (Blueprint $table) => $table->dropColumn(['object', 'competencies', 'prodi_code']));
    }
};
