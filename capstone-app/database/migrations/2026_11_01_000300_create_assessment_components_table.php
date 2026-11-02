<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F13 — bentuk penilaian menjadi master data yang dapat ditambah/diubah admin (sebelumnya tetap di config).
 * Diisi awal dari config capstone.default_components; jadwal per periode tetap di assessment_windows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_components', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('label', 100);
            $table->json('roles');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $order = 0;
        foreach (config('capstone.default_components') as $key => $meta) {
            DB::table('assessment_components')->insert([
                'key' => $key, 'label' => $meta['label'], 'roles' => json_encode([$meta['role']]),
                'sort_order' => $order++, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_components');
    }
};
