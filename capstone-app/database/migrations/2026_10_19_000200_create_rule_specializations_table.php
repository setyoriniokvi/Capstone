<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Daftar peminatan per prodi pada periode (bagian aturan registrasi); dipilih dosen sebagai kompetensi topik. */
    public function up(): void
    {
        Schema::create('rule_specializations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_rule_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->timestamps();
            $table->unique(['registration_rule_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_specializations');
    }
};
