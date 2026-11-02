<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F7 — kata kunci terlarang dipisah untuk topik mandiri dan topik dosen. Kata kunci lama berlaku untuk keduanya,
 * jadi disalin ke dua daftar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forbidden_keywords', function (Blueprint $table) {
            $table->dropUnique(['keyword']);
            $table->string('source', 10)->default('mandiri')->after('id');
            $table->unique(['source', 'keyword']);
        });

        foreach (DB::table('forbidden_keywords')->where('source', 'mandiri')->pluck('keyword') as $keyword) {
            DB::table('forbidden_keywords')->insert(['source' => 'dosen', 'keyword' => $keyword, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('forbidden_keywords')->where('source', 'dosen')->delete();
        Schema::table('forbidden_keywords', function (Blueprint $table) {
            $table->dropUnique(['source', 'keyword']);
            $table->dropColumn('source');
            $table->unique('keyword');
        });
    }
};
