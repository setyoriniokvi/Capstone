<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aturan registrasi kini satu per periode (diatur saat membuat/mengubah periode), bukan per prodi.
     * Data lama digabung: aturan pertama tiap periode dipertahankan, mata kuliah prasyarat & peminatan
     * dari aturan prodi lain dipindahkan ke aturan tersebut (tanpa duplikat), lalu aturan lain dihapus.
     */
    public function up(): void
    {
        Schema::table('registration_rules', function (Blueprint $table) {
            $table->string('prodi_code', 10)->nullable()->change();
        });

        foreach (DB::table('registration_rules')->select('period_id')->distinct()->pluck('period_id') as $periodId) {
            $rules = DB::table('registration_rules')->where('period_id', $periodId)->orderBy('id')->get();
            $keep = $rules->first();

            foreach ($rules->skip(1) as $rule) {
                foreach (DB::table('prerequisite_courses')->where('registration_rule_id', $rule->id)->get() as $course) {
                    $exists = DB::table('prerequisite_courses')->where('registration_rule_id', $keep->id)->where('code', $course->code)->exists();
                    $exists ? DB::table('prerequisite_courses')->where('id', $course->id)->delete()
                        : DB::table('prerequisite_courses')->where('id', $course->id)->update(['registration_rule_id' => $keep->id]);
                }
                foreach (DB::table('rule_specializations')->where('registration_rule_id', $rule->id)->get() as $spec) {
                    $exists = DB::table('rule_specializations')->where('registration_rule_id', $keep->id)->where('name', $spec->name)->exists();
                    $exists ? DB::table('rule_specializations')->where('id', $spec->id)->delete()
                        : DB::table('rule_specializations')->where('id', $spec->id)->update(['registration_rule_id' => $keep->id]);
                }
                DB::table('registration_rules')->where('id', $rule->id)->delete();
            }

            DB::table('registration_rules')->where('id', $keep->id)->update(['prodi_code' => null]);
        }
    }

    public function down(): void
    {
        // Penggabungan aturan tidak dapat dikembalikan ke per prodi.
    }
};
