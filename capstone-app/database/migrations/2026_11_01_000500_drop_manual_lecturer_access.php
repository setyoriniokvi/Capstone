<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F3 — hak akses dosen: semua dosen berhak akses "Dosen"; Pembimbing / Penguji tercentang otomatis saat diplot
 * (dan tetap dapat diubah admin). Kategori "dosen kelas" dihapus beserta kuota per kategori.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lecturer_profiles', fn (Blueprint $table) => $table->dropColumn('is_class_lecturer'));
        DB::table('load_quotas')->where('scope', 'kategori')->delete();

        // Mulai dari kosong, lalu centang sesuai plotting pembimbing / penguji yang sudah ada.
        DB::table('lecturer_profiles')->update(['can_supervise' => false, 'can_examine' => false]);
        foreach (['pembimbing' => 'can_supervise', 'penguji' => 'can_examine'] as $role => $column) {
            DB::table('lecturer_profiles')
                ->whereIn('user_id', DB::table('team_lecturers')->where('role', $role)->select('lecturer_id'))
                ->update([$column => true]);
        }
    }

    public function down(): void
    {
        Schema::table('lecturer_profiles', fn (Blueprint $table) => $table->boolean('is_class_lecturer')->default(false));
    }
};
