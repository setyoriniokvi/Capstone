<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Master Data: log setiap data mahasiswa / dosen yang masuk, berubah (nilai sebelum & sesudah), atau dihapus. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_data_logs', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 10)->index();              // mahasiswa | dosen
            $table->unsignedBigInteger('subject_user_id')->nullable()->index(); // tanpa FK: log tetap ada walau akun dihapus
            $table->string('subject_label');                       // nama orang / tim saat log dibuat
            $table->string('entity', 40)->index();                 // Akun Mahasiswa, Tim, Dokumen, ...
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('event', 10)->index();                  // created | updated | deleted
            $table->string('description')->nullable();
            $table->json('changes')->nullable();                   // [{field, label, before, after}]
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_label')->nullable();             // "Nama (admin)" atau "Sistem"
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_logs');
    }
};
