<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // F50 / F56 — ambang waktu pengingat & jenis pekerjaan yang dipantau
        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->string('job_type', 40);
            $table->unsignedSmallInteger('hours_before');
            $table->json('channels');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['job_type', 'hours_before']);
        });

        // F12 / F54 / F55 — log pengiriman notifikasi email & WhatsApp
        Schema::create('reminder_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 191)->unique();
            $table->string('job_type', 40);
            $table->string('job_ref', 120)->index();
            $table->foreignId('lecturer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reminder_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('trigger', ['otomatis', 'manual']);
            $table->enum('channel', ['email', 'whatsapp']);
            $table->string('recipient', 150);
            $table->json('payload');
            $table->enum('status', ['antre', 'diproses', 'terkirim', 'gagal', 'dibatalkan'])->default('antre')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('deadline_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_dispatches');
        Schema::dropIfExists('reminder_rules');
    }
};
