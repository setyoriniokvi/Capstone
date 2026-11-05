<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F9 — kuota dosen cukup minimum & maksimum; nilai default tidak dipakai perhitungan apa pun dan membingungkan admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('load_quotas', function (Blueprint $table) {
            $table->dropColumn(['supervise_default', 'examine_default']);
        });
    }

    public function down(): void
    {
        Schema::table('load_quotas', function (Blueprint $table) {
            $table->unsignedSmallInteger('supervise_default')->default(0);
            $table->unsignedSmallInteger('examine_default')->default(0);
        });
    }
};
