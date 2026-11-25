<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F5 — daftar kelas per periode (diatur admin di Aturan registrasi tim, satu per baris) dan kelas mahasiswa
 * (dipilih saat registrasi akun & tim; dipakai kolom CLASS ekspor iGadis). Periode aktif diisi SI-47-01 … SI-47-10 dan SI-47-INT.
 */
return new class extends Migration
{
    public const CURRENT_CLASSES = [
        'SI-47-01', 'SI-47-02', 'SI-47-03', 'SI-47-04', 'SI-47-05',
        'SI-47-06', 'SI-47-07', 'SI-47-08', 'SI-47-09', 'SI-47-10', 'SI-47-INT',
    ];

    public function up(): void
    {
        Schema::table('registration_rules', function (Blueprint $table) {
            $table->json('classes')->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('class_name', 30)->nullable()->after('nim');
        });

        $active = DB::table('periods')->where('is_active', true)->pluck('id');
        DB::table('registration_rules')->whereIn('period_id', $active)->update(['classes' => json_encode(self::CURRENT_CLASSES)]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('class_name');
        });
        Schema::table('registration_rules', function (Blueprint $table) {
            $table->dropColumn('classes');
        });
    }
};
