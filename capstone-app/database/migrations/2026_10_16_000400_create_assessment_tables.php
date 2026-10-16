<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // F13 — resume penilaian per CLO
        Schema::create('clos', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->text('description');
            $table->string('assessment_scheme', 150)->nullable();
            $table->decimal('weight', 5, 2);
            $table->timestamps();
        });

        Schema::create('clo_component_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clo_id')->constrained()->cascadeOnDelete();
            $table->string('component', 30);
            $table->decimal('weight', 5, 2);
            $table->unique(['clo_id', 'component']);
        });

        // F14 — indikator penilaian
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clo_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 255);
            $table->string('evidence', 255)->nullable();
            $table->decimal('weight', 5, 2);
            $table->string('scheme', 20);
            $table->timestamps();
        });

        Schema::create('indicator_component_weights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $table->string('component', 30);
            $table->decimal('weight', 5, 2);
            $table->unique(['indicator_id', 'component']);
        });

        // F15 — rubrikasi dokumen
        Schema::create('document_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('assessment_points');
            $table->text('level_5');
            $table->text('level_4');
            $table->text('level_3');
            $table->text('level_2');
            $table->text('level_1');
            $table->text('level_0');
            $table->text('example_level_5')->nullable();
            $table->timestamps();
        });

        // F16 — rubrikasi penilaian lisan
        Schema::create('oral_rubric_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('level')->unique();
            $table->decimal('factor', 3, 2);
            $table->string('label', 60);
            $table->text('behavior');
            $table->timestamps();
        });

        // F47 / F60 — penilaian dosen
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assessor_id')->constrained('users')->cascadeOnDelete();
            $table->string('component', 30);
            $table->enum('assessor_role', ['pembimbing', 'penguji']);
            $table->enum('status', ['draft', 'final'])->default('draft')->index();
            $table->text('feedback')->nullable();
            $table->enum('channel', ['web', 'qr'])->default('web');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['team_id', 'component', 'assessor_id']);
        });

        Schema::create('assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('indicator_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('oral_level')->nullable();
            $table->decimal('factor', 3, 2)->default(1);
            $table->text('note')->nullable();
            $table->unique(['assessment_id', 'indicator_id']);
        });

        // F28 — peer review
        Schema::create('peer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete();
            $table->string('aspect', 30);
            $table->unsignedTinyInteger('score');
            $table->timestamps();
            $table->unique(['reviewer_id', 'reviewee_id', 'aspect']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_reviews');
        Schema::dropIfExists('assessment_scores');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('oral_rubric_levels');
        Schema::dropIfExists('document_rubrics');
        Schema::dropIfExists('indicator_component_weights');
        Schema::dropIfExists('indicators');
        Schema::dropIfExists('clo_component_weights');
        Schema::dropIfExists('clos');
    }
};
