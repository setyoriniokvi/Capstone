<?php

use App\Services\PeriodMasterData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Dua admin: admin fakultas (role admin, CRUD periode) dan admin prodi (role admin_prodi, satu akun per periode —
 * satu akun boleh dipakai beberapa periode lewat periods.admin_user_id). Data master menjadi milik periode:
 * baris lama diberikan ke periode aktif (atau periode pertama), disalin ke periode lain, dan disalin sekali lagi
 * sebagai data bawaan (period_id NULL) untuk periode baru. Dosen & mahasiswa dicatat per periode (period_users).
 */
return new class extends Migration
{
    /** tabel => [unique lama, unique baru (tanpa period_id)] */
    private const UNIQUES = [
        'assessment_components' => [['key'], ['key']],
        'clos' => [['code'], ['code']],
        'indicators' => [['code'], ['code']],
        'oral_rubric_levels' => [['level'], ['level']],
        'peer_aspects' => [['key'], ['key']],
        'score_rules' => [['key'], ['key']],
        'topic_instructions' => [['source'], ['source']],
        'forbidden_keywords' => [['source', 'keyword'], ['source', 'keyword']],
        'reminder_rules' => [['job_type', 'component', 'hours_before'], ['job_type', 'component', 'hours_before']],
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->change();
        });
        Schema::table('periods', function (Blueprint $table) {
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        foreach (PeriodMasterData::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->foreignId('period_id')->nullable()->constrained()->cascadeOnDelete();
                if (isset(self::UNIQUES[$name])) {
                    $table->dropUnique(self::UNIQUES[$name][0]);
                    $table->unique(['period_id', ...self::UNIQUES[$name][1]]);
                } else {
                    $table->index('period_id');
                }
            });
        }

        Schema::create('period_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['period_id', 'user_id']);
        });

        $this->assignExistingData();
    }

    private function assignExistingData(): void
    {
        $periods = DB::table('periods')->orderByDesc('is_active')->orderBy('id')->pluck('id');
        if ($periods->isEmpty()) {
            return; // data lama tetap sebagai data bawaan
        }
        $primary = $periods->first();
        $copier = new PeriodMasterData;

        // Data lama → periode utama (nilai yang sudah ada merujuk id indikator lama); salinan → periode lain & bawaan.
        $original = collect(PeriodMasterData::TABLES)->mapWithKeys(fn ($t) => [$t => DB::table($t)->whereNull('period_id')->pluck('id')]);
        foreach ($periods->slice(1) as $period) {
            $copier->copy(null, $period);
        }
        $copier->copy(null, null);
        foreach ($original as $table => $ids) {
            foreach ($ids->chunk(500) as $chunk) {
                DB::table($table)->whereIn('id', $chunk)->update(['period_id' => $primary]);
            }
        }

        // Dosen terdaftar di semua periode yang ada; mahasiswa di periode timnya (tanpa tim → periode utama).
        $now = now();
        $rows = [];
        foreach (DB::table('users')->where('role', 'dosen')->get(['id', 'is_active']) as $lecturer) {
            foreach ($periods as $period) {
                $rows[] = ['period_id' => $period, 'user_id' => $lecturer->id, 'is_active' => $lecturer->is_active, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach (DB::table('users')->where('role', 'mahasiswa')->get(['id', 'is_active']) as $student) {
            $teamPeriods = DB::table('team_members')->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->where('team_members.user_id', $student->id)->distinct()->pluck('teams.period_id');
            foreach ($teamPeriods->whenEmpty(fn () => collect([$primary])) as $period) {
                $rows[] = ['period_id' => $period, 'user_id' => $student->id, 'is_active' => $student->is_active, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('period_users')->insertOrIgnore($chunk);
        }

        // Koneksi Google Drive (OAuth) kini per periode: koneksi lama dipakai semua periode yang ada.
        $disk = Storage::disk('local');
        if ($disk->exists('google-drive-oauth.json')) {
            foreach ($periods as $period) {
                $disk->put("google-drive-oauth-{$period}.json", $disk->get('google-drive-oauth.json'));
            }
            $disk->delete('google-drive-oauth.json');
        }
    }

    public function down(): void
    {
        // Data master yang sudah dipisah per periode tidak digabung kembali: hanya data periode utama yang dipertahankan.
        $primary = DB::table('periods')->orderByDesc('is_active')->orderBy('id')->value('id');
        foreach (PeriodMasterData::TABLES as $name) {
            if (! in_array($name, ['support_files', 'master_data_logs'], true)) {
                DB::table($name)->where(fn ($q) => $q->whereNull('period_id')->when($primary, fn ($q) => $q->orWhere('period_id', '!=', $primary)))->delete();
            }
            Schema::table($name, function (Blueprint $table) use ($name) {
                if (isset(self::UNIQUES[$name])) {
                    $table->dropUnique(['period_id', ...self::UNIQUES[$name][1]]);
                    $table->unique(self::UNIQUES[$name][0]);
                } else {
                    $table->dropIndex(['period_id']);
                }
                $table->dropConstrainedForeignId('period_id');
            });
        }
        Schema::dropIfExists('period_users');
        Schema::table('periods', fn (Blueprint $table) => $table->dropConstrainedForeignId('admin_user_id'));
        DB::table('users')->where('role', 'admin_prodi')->delete();
    }
};
