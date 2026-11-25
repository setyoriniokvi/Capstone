<?php

use App\Models\ReminderRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F50 / F52 — reminder otomatis per jenis deadline yang lebih rinci (penilaian per bentuk penilaian) dengan pesan
 * yang ditulis admin. Aturan "penilaian" lama (semua bentuk penilaian) dipecah menjadi satu aturan per bentuk penilaian
 * yang dinilai dosen; aturan lama diberi pesan bawaan agar tetap dapat dikirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_rules', function (Blueprint $table) {
            $table->string('component', 40)->nullable()->after('job_type');
            $table->text('message')->nullable()->after('channels');
            $table->dropUnique(['job_type', 'hours_before']);
            $table->unique(['job_type', 'component', 'hours_before']);
        });

        $components = collect(config('capstone.components') ?: config('capstone.default_components'))
            ->reject(fn ($c) => in_array('mahasiswa', $c['roles'] ?? [$c['role'] ?? null], true))->keys();

        foreach (DB::table('reminder_rules')->get() as $rule) {
            if ($rule->job_type === 'penilaian' && $rule->component === null) {
                foreach ($components as $component) {
                    DB::table('reminder_rules')->insertOrIgnore([
                        'job_type' => 'penilaian', 'component' => $component, 'hours_before' => $rule->hours_before,
                        'channels' => $rule->channels, 'message' => ReminderRule::DEFAULT_MESSAGE, 'is_active' => $rule->is_active,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                DB::table('reminder_rules')->where('id', $rule->id)->delete();
            } elseif ($rule->message === null) {
                DB::table('reminder_rules')->where('id', $rule->id)->update(['message' => ReminderRule::DEFAULT_MESSAGE]);
            }
        }
    }

    public function down(): void
    {
        DB::table('reminder_rules')->where('job_type', 'penilaian')->whereNotNull('component')->delete();
        Schema::table('reminder_rules', function (Blueprint $table) {
            $table->dropUnique(['job_type', 'component', 'hours_before']);
            $table->unique(['job_type', 'hours_before']);
            $table->dropColumn(['component', 'message']);
        });
    }
};
