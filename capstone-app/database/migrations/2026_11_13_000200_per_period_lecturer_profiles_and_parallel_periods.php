<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profil dosen per periode: kode dosen, NIP, peminatan, hak Pembimbing/Penguji, dan nomor WhatsApp dapat berbeda di
 * setiap periode (email, nama, password tetap satu akun). Profil lama disalin ke setiap periode tempat dosen terdaftar.
 * Beberapa periode boleh aktif bersamaan, sehingga antrean reminder mencatat periodenya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturer_profiles', function (Blueprint $table) {
            $table->foreignId('period_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phone', 30)->nullable();
            $table->dropUnique(['user_id']);
            $table->dropUnique(['lecturer_code']);
            $table->dropUnique(['nip']);
            $table->unique(['period_id', 'user_id']);
            $table->unique(['period_id', 'lecturer_code']);
            $table->unique(['period_id', 'nip']);
        });
        Schema::table('reminder_dispatches', function (Blueprint $table) {
            $table->foreignId('period_id')->nullable()->constrained()->cascadeOnDelete();
        });

        $now = now();
        foreach (DB::table('lecturer_profiles')->whereNull('period_id')->get() as $profile) {
            $periods = DB::table('period_users')->where('user_id', $profile->user_id)->orderBy('period_id')->pluck('period_id');
            if ($periods->isEmpty()) {
                continue;
            }
            $phone = DB::table('users')->where('id', $profile->user_id)->value('phone');
            DB::table('lecturer_profiles')->where('id', $profile->id)->update(['period_id' => $periods->first(), 'phone' => $phone]);
            foreach ($periods->slice(1) as $period) {
                $row = (array) $profile;
                unset($row['id']);
                DB::table('lecturer_profiles')->insert(['period_id' => $period, 'phone' => $phone, 'created_at' => $now, 'updated_at' => $now] + $row);
            }
        }

        $primary = DB::table('periods')->where('is_active', true)->orderByDesc('starts_on')->value('id')
            ?? DB::table('periods')->orderBy('id')->value('id');
        if ($primary) {
            DB::table('reminder_dispatches')->whereNull('period_id')->update(['period_id' => $primary]);
        }
    }

    public function down(): void
    {
        Schema::table('reminder_dispatches', fn (Blueprint $table) => $table->dropConstrainedForeignId('period_id'));
        // Satu profil per dosen: profil periode paling awal dipertahankan.
        $keep = DB::table('lecturer_profiles')->selectRaw('min(id) as id')->groupBy('user_id')->pluck('id');
        DB::table('lecturer_profiles')->whereNotIn('id', $keep)->delete();
        Schema::table('lecturer_profiles', function (Blueprint $table) {
            $table->dropUnique(['period_id', 'user_id']);
            $table->dropUnique(['period_id', 'lecturer_code']);
            $table->dropUnique(['period_id', 'nip']);
            $table->dropConstrainedForeignId('period_id');
            $table->dropColumn('phone');
            $table->unique('user_id');
            $table->unique('lecturer_code');
            $table->unique('nip');
        });
    }
};
