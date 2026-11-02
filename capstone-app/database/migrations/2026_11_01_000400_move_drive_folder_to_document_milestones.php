<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F35 — folder Google Drive Database tidak lagi satu per periode, melainkan satu per milestone dokumen.
 * Folder periode lama dipakai sebagai nilai awal folder setiap milestone dokumen periode tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('milestones', fn (Blueprint $table) => $table->string('drive_folder_id', 128)->nullable()->after('allowed_types'));

        foreach (DB::table('periods')->whereNotNull('drive_root_folder_id')->get(['id', 'drive_root_folder_id']) as $period) {
            DB::table('milestones')->where('period_id', $period->id)->where('kind', 'dokumen')
                ->update(['drive_folder_id' => $period->drive_root_folder_id]);
        }

        Schema::table('periods', fn (Blueprint $table) => $table->dropColumn('drive_root_folder_id'));
    }

    public function down(): void
    {
        Schema::table('periods', fn (Blueprint $table) => $table->string('drive_root_folder_id', 128)->nullable());

        foreach (DB::table('milestones')->whereNotNull('drive_folder_id')->get(['period_id', 'drive_folder_id']) as $milestone) {
            DB::table('periods')->where('id', $milestone->period_id)->whereNull('drive_root_folder_id')
                ->update(['drive_root_folder_id' => $milestone->drive_folder_id]);
        }

        Schema::table('milestones', fn (Blueprint $table) => $table->dropColumn('drive_folder_id'));
    }
};
