<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F27 — catatan tambahan (opsional) pada log bimbingan, mis. tautan dokumen atau lampiran terkait bimbingan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('activity');
        });
    }

    public function down(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
