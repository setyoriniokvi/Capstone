<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hak akses "Dosen Kelas" (per periode, di profil dosen): dipakai cakupan kuota dosen kelas
 * (load_quotas scope "kategori", category "dosen_kelas").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturer_profiles', function (Blueprint $table) {
            $table->boolean('is_class_lecturer')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('lecturer_profiles', function (Blueprint $table) {
            $table->dropColumn('is_class_lecturer');
        });
    }
};
