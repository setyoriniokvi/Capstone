<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // F35 — folder Google Drive per mahasiswa
        Schema::create('student_drive_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->string('folder_id', 128);
            $table->string('folder_name');
            $table->timestamps();
            $table->unique(['user_id', 'period_id']);
        });

        // F26 / F33 / F34 / F39 — dokumen milestone
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('milestone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('owner_email', 150);
            $table->string('document_type', 30);
            $table->unsignedInteger('current_revision')->default(0);
            $table->foreignId('current_version_id')->nullable();
            $table->string('drive_file_id', 128)->nullable();
            $table->string('drive_folder_id', 128)->nullable();
            $table->enum('review_status', ['dikumpulkan', 'ditinjau', 'revisi', 'disetujui'])->default('dikumpulkan');
            $table->text('feedback')->nullable();
            $table->foreignId('feedback_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('feedback_at')->nullable();
            $table->boolean('is_late')->default(false);
            $table->enum('state', ['aktif', 'trash', 'dihapus'])->default('aktif')->index();
            $table->timestamps();
            $table->unique(['team_id', 'milestone_id', 'owner_id']);
        });

        // F37 / F39 / F40 / F41 — riwayat revisi & status sinkronisasi
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->string('local_path');
            $table->char('checksum', 64);
            $table->string('idempotency_key', 64)->unique();
            $table->enum('sync_action', ['create', 'update']);
            $table->enum('sync_status', ['menunggu', 'proses', 'berhasil', 'gagal', 'dilewati'])->default('menunggu')->index();
            $table->unsignedTinyInteger('sync_attempts')->default(0);
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['document_id', 'revision_no']);
        });

        // F40 — jejak setiap operasi ke Google Drive
        Schema::create('drive_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 20);
            $table->enum('status', ['berhasil', 'gagal']);
            $table->text('error')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // F27 / F46 — log bimbingan
        Schema::create('logbooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('session_no');
            $table->date('session_date');
            $table->text('activity');
            $table->string('responsible_person', 150);
            $table->text('instrument')->nullable();
            $table->text('data_collected')->nullable();
            $table->text('output');
            $table->enum('status', ['menunggu', 'disetujui', 'revisi'])->default('menunggu')->index();
            $table->text('feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('logbook_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logbook_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20);
            $table->json('snapshot');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logbook_histories');
        Schema::dropIfExists('logbooks');
        Schema::dropIfExists('drive_operations');
        Schema::dropIfExists('document_versions');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('student_drive_folders');
    }
};
