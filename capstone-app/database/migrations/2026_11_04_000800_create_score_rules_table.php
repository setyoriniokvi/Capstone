<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F14 — informasi "Cara nilai dihitung" (kartu di atas tabel Indikator, hanya dibaca): rumus, skor, dan faktor
 * pemahaman dari "Rubrik Capstone Design Project.xlsx". Baris faktor pemahaman menyimpan aturan yang dipakai
 * perhitungan nilai — skema yang memakai faktor dari Rubrik Lisan dan faktor untuk indikator tanpa verifikasi lisan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('score_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->nullable()->unique();
            $table->string('label', 100);
            $table->text('description');
            $table->json('settings')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('score_rules')->insert([
            ['key' => 'formula', 'label' => 'Rumus', 'sort_order' => 1, 'settings' => null, 'created_at' => $now, 'updated_at' => $now,
                'description' => 'Nilai indikator = (Skor / 5) x Bobot x Faktor Pemahaman'],
            ['key' => 'score', 'label' => 'Skor', 'sort_order' => 2, 'settings' => null, 'created_at' => $now, 'updated_at' => $now,
                'description' => '1-5 dari Rubrik Dokumen. Untuk indikator yang dinilai per kelompok, diisi sekali di kolom Skor Kelompok. Untuk indikator perorangan (peer review, sidang, pameran), diisi di kolom Skor Indiv mahasiswa yang bersangkutan - nilai ini menimpa skor kelompok.'],
            ['key' => 'oral_factor', 'label' => 'Faktor Pemahaman', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now,
                'description' => '0,25-1,00 dari Rubrik Lisan, diisi PER MAHASISWA. Inilah gerbang anti-AI: dokumen dinilai utuh, lalu dikalikan seberapa mampu mahasiswa itu mempertahankannya. Untuk indikator tanpa verifikasi lisan, biarkan 1,00.',
                // Sama dengan perilaku sebelumnya: faktor dari Rubrik Lisan untuk skema Lisan & Dokumen + Lisan, lainnya 1,00.
                'settings' => json_encode(['schemes' => ['lisan', 'dokumen_lisan'], 'default_factor' => 1])],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('score_rules');
    }
};
