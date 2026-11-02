<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Aturan tambahan per periode (sebelumnya tetap di config): minimal log bimbingan & pembuka peer review. */
    public function up(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_approved_logbooks')->default(8)->after('drive_root_folder_id');
            $table->boolean('peer_review_after_final_report')->default(true)->after('min_approved_logbooks');
        });
    }

    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->dropColumn(['min_approved_logbooks', 'peer_review_after_final_report']);
        });
    }
};
