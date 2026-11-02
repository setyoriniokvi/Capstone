<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Semua dosen kini dapat mengusulkan topik, jadi hak akses "pengusul topik" tidak diperlukan lagi. */
    public function up(): void
    {
        Schema::table('lecturer_profiles', function (Blueprint $table) {
            $table->dropColumn('can_propose_topic');
        });
    }

    public function down(): void
    {
        Schema::table('lecturer_profiles', function (Blueprint $table) {
            $table->boolean('can_propose_topic')->default(false);
        });
    }
};
