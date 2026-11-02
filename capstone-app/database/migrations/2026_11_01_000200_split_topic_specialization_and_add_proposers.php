<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Topik dosen: peminatan dipisah dari kompetensi yang diharapkan (teks panjang); peminatan, program studi, dan dosen
 * pengusul masing-masing boleh lebih dari satu (pengusul utama = pembuat topik, lecturer_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->json('specializations')->nullable()->after('competencies');
            $table->json('prodi_codes')->nullable()->after('specializations');
        });

        Schema::create('topic_proposers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['topic_id', 'lecturer_id']);
        });

        // Isian lama "Kompetensi yang diharapkan" pada topik dosen adalah peminatan.
        foreach (DB::table('topics')->where('source', 'dosen')->get() as $topic) {
            DB::table('topics')->where('id', $topic->id)->update([
                'specializations' => $topic->competencies ? json_encode([$topic->competencies]) : null,
                'competencies' => null,
                'prodi_codes' => $topic->prodi_code ? json_encode([$topic->prodi_code]) : null,
            ]);
            if ($topic->lecturer_id) {
                DB::table('topic_proposers')->insert(['topic_id' => $topic->id, 'lecturer_id' => $topic->lecturer_id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        Schema::table('topics', fn (Blueprint $table) => $table->dropColumn('prodi_code'));
    }

    public function down(): void
    {
        Schema::table('topics', fn (Blueprint $table) => $table->string('prodi_code', 10)->nullable()->after('competencies'));

        foreach (DB::table('topics')->where('source', 'dosen')->get() as $topic) {
            DB::table('topics')->where('id', $topic->id)->update([
                'competencies' => json_decode($topic->specializations ?? '[]', true)[0] ?? null,
                'prodi_code' => json_decode($topic->prodi_codes ?? '[]', true)[0] ?? null,
            ]);
        }

        Schema::dropIfExists('topic_proposers');
        Schema::table('topics', fn (Blueprint $table) => $table->dropColumn(['specializations', 'prodi_codes']));
    }
};
