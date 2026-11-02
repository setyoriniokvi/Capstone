<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Peer review terbuka selama periode berlangsung; aturan "dibuka setelah Laporan Akhir" dihapus. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->dropColumn('peer_review_after_final_report');
        });
    }

    public function down(): void
    {
        Schema::table('periods', function (Blueprint $table) {
            $table->boolean('peer_review_after_final_report')->default(true)->after('min_approved_logbooks');
        });
    }
};
