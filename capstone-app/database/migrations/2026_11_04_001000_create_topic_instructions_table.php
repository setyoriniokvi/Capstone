<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F7 — instruksi (teks, opsional) per jenis topik, tampil di bagian atas halaman topik mandiri / topik dosen. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_instructions', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->unique(); // mandiri | dosen
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_instructions');
    }
};
