<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Folder Google Drive kini satu per periode (periods.drive_root_folder_id), bukan per prodi.
     * Periode yang belum punya folder induk memakai folder prodi pertama yang pernah diisi.
     */
    public function up(): void
    {
        foreach (DB::table('period_drive_folders')->orderBy('id')->get()->groupBy('period_id') as $periodId => $folders) {
            DB::table('periods')->where('id', $periodId)->whereNull('drive_root_folder_id')
                ->update(['drive_root_folder_id' => $folders->first()->folder_id]);
        }

        Schema::dropIfExists('period_drive_folders');
    }

    public function down(): void
    {
        Schema::create('period_drive_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained()->cascadeOnDelete();
            $table->string('prodi_code', 10);
            $table->string('folder_id', 128);
            $table->timestamps();
            $table->unique(['period_id', 'prodi_code']);
        });
    }
};
