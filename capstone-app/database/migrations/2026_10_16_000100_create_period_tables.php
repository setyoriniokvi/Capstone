<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // F2 — Periode & timeline
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('academic_year', 9);
            $table->enum('semester', ['ganjil', 'genap']);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(false)->index();
            $table->string('drive_root_folder_id', 128)->nullable();
            $table->timestamps();
        });

        // F2 — routing folder external drive per prodi
        Schema::create('period_drive_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->string('prodi_code', 10);
            $table->string('folder_id', 128);
            $table->timestamps();
            $table->unique(['period_id', 'prodi_code']);
        });

        // F2 — milestone & tenggat setiap tahapan
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('kind', 30);
            $table->string('document_type', 30)->nullable();
            $table->unsignedTinyInteger('week_no')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('due_at');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['period_id', 'kind']);
        });

        // Jendela pengisian nilai per bentuk penilaian (F47, F49, F61)
        Schema::create('assessment_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->string('component', 30);
            $table->dateTime('opens_at');
            $table->dateTime('due_at');
            $table->timestamps();
            $table->unique(['period_id', 'component']);
        });

        // F5 — aturan registrasi per prodi
        Schema::create('registration_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->string('prodi_code', 10);
            $table->unsignedSmallInteger('min_sks')->default(0);
            $table->unsignedTinyInteger('min_members')->default(2);
            $table->unsignedTinyInteger('max_members')->default(3);
            $table->boolean('require_distinct_region')->default(true);
            $table->boolean('require_distinct_specialization')->default(true);
            $table->timestamps();
            $table->unique(['period_id', 'prodi_code']);
        });

        Schema::create('prerequisite_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_rule_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name', 150);
            $table->timestamps();
            $table->unique(['registration_rule_id', 'code']);
        });

        // F9 — kuota beban dosen (global / kategori / per dosen)
        Schema::create('load_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->enum('scope', ['global', 'kategori', 'dosen']);
            $table->enum('category', ['dosen_kelas', 'non_pengampu'])->nullable();
            $table->foreignId('lecturer_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('supervise_default')->default(0);
            $table->unsignedSmallInteger('supervise_min')->default(0);
            $table->unsignedSmallInteger('supervise_max')->default(0);
            $table->unsignedSmallInteger('examine_default')->default(0);
            $table->unsignedSmallInteger('examine_min')->default(0);
            $table->unsignedSmallInteger('examine_max')->default(0);
            $table->timestamps();
            $table->unique(['period_id', 'scope', 'category', 'lecturer_id']);
        });

        // F17 / F20 / F45 — file pendukung
        Schema::create('support_files', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('category', 60);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_files');
        Schema::dropIfExists('load_quotas');
        Schema::dropIfExists('prerequisite_courses');
        Schema::dropIfExists('registration_rules');
        Schema::dropIfExists('assessment_windows');
        Schema::dropIfExists('milestones');
        Schema::dropIfExists('period_drive_folders');
        Schema::dropIfExists('periods');
    }
};
