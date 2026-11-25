<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F28 — rubrik peer review yang dapat diatur admin (menu Rubrik Peer Review): aspek penilaian, definisi, dan deskripsi
 * nilai 1–5. Diisi awal dari rubrik bawaan (config/peer_rubric.php & capstone.peer_aspects, 6 aspek kerja tim).
 * "key" dipakai peer_reviews.aspect dan tidak berubah saat aspek diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peer_aspects', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('name', 150);
            $table->text('definition');
            foreach (range(1, 5) as $level) {
                $table->text("level_{$level}");
            }
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $rubric = require config_path('peer_rubric.php');
        $order = 0;
        foreach (config('capstone.peer_aspects') as $key => $name) {
            $levels = $rubric[$key]['levels'] ?? [];
            DB::table('peer_aspects')->insert([
                'key' => $key, 'name' => $name, 'definition' => $rubric[$key]['definition'] ?? $name,
                'level_1' => $levels[1] ?? '-', 'level_2' => $levels[2] ?? '-', 'level_3' => $levels[3] ?? '-',
                'level_4' => $levels[4] ?? '-', 'level_5' => $levels[5] ?? '-',
                'sort_order' => ++$order, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_aspects');
    }
};
