<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // F21 / F6 — tim
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('prodi_code', 10)->index();
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['draft', 'menunggu', 'diterima', 'ditolak'])->default('draft')->index();
            $table->json('screening')->nullable();
            $table->unsignedTinyInteger('screening_score')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('final_topic_id')->nullable();
            $table->timestamps();
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_leader')->default(false);
            $table->enum('invite_status', ['diundang', 'diterima', 'ditolak'])->default('diundang');
            $table->string('khs_path')->nullable();
            $table->string('domicile_path')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
        });

        // F31 / F32 — referensi QR di backend
        Schema::create('team_qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // F7 / F23 / F42 — topik
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->enum('source', ['mandiri', 'dosen'])->index();
            $table->string('title', 255);
            $table->text('description');
            $table->string('field', 120)->nullable();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('quota')->default(1);
            $table->enum('status', ['menunggu', 'diterima', 'ditolak'])->default('menunggu')->index();
            $table->text('review_notes')->nullable();
            $table->json('keyword_hits')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('topic_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->text('motivation')->nullable();
            $table->enum('status', ['menunggu', 'diterima', 'ditolak', 'dibatalkan'])->default('menunggu')->index();
            $table->text('notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('forbidden_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 100)->unique();
            $table->timestamps();
        });

        // F10 — plotting dosen pembimbing & penguji
        Schema::create('team_lecturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['pembimbing', 'penguji']);
            $table->enum('source', ['sistem', 'admin'])->default('admin');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'role']);
            $table->index(['lecturer_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_lecturers');
        Schema::dropIfExists('forbidden_keywords');
        Schema::dropIfExists('topic_applications');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('team_qr_tokens');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
    }
};
