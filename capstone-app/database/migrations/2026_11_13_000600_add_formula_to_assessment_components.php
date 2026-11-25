<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rumus nilai indikator per bentuk penilaian (per periode), disusun admin dari pilihan baku:
 * {divisor: max|fixed, fixed: angka, weight: bool, oral: bool, multiplier: angka}. Kosong = rumus bawaan
 * (Interval ÷ Interval maksimum) × Bobot × Faktor Pemahaman.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_components', function (Blueprint $table) {
            $table->json('formula')->nullable()->after('roles');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_components', function (Blueprint $table) {
            $table->dropColumn('formula');
        });
    }
};
