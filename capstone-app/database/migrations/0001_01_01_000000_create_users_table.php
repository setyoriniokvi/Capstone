<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_programs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->string('google_id', 64)->nullable()->unique();
            $table->string('avatar_url')->nullable();
            $table->enum('role', ['admin', 'mahasiswa', 'dosen'])->index();
            $table->string('prodi_code', 10)->nullable()->index();
            $table->string('phone', 20)->nullable();
            $table->boolean('is_active')->default(true);

            // Data akademik mahasiswa (F4, F5, F19, F21)
            $table->string('nim', 20)->nullable()->unique();
            $table->unsignedSmallInteger('sks_passed')->nullable();
            $table->json('passed_courses')->nullable();
            $table->string('origin_region', 100)->nullable();
            $table->string('specialization', 100)->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Data dosen (F3)
        Schema::create('lecturer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('lecturer_code', 10)->unique();
            $table->string('nip', 30)->nullable()->unique();
            $table->string('specialization', 150)->nullable();
            $table->boolean('can_propose_topic')->default(false);
            $table->boolean('can_supervise')->default(false);
            $table->boolean('can_examine')->default(false);
            $table->boolean('is_class_lecturer')->default(false);
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->nullableMorphs('subject');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('lecturer_profiles');
        Schema::dropIfExists('users');
        Schema::dropIfExists('study_programs');
    }
};
