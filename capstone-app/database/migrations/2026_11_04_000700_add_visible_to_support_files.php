<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F17 / F20 / F45 — role yang dapat melihat file pendukung (dosen dan/atau mahasiswa; admin selalu dapat melihat).
 * File yang sudah ada tetap terlihat oleh keduanya, seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_files', function (Blueprint $table) {
            $table->json('visible_to')->nullable()->after('category');
        });
        DB::table('support_files')->update(['visible_to' => json_encode(['dosen', 'mahasiswa'])]);
    }

    public function down(): void
    {
        Schema::table('support_files', function (Blueprint $table) {
            $table->dropColumn('visible_to');
        });
    }
};
