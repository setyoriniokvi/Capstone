<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Minggu milestone kini selalu dihitung dari tanggal deadline, tidak lagi diisi admin. */
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropColumn('week_no');
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->unsignedTinyInteger('week_no')->nullable()->after('document_type');
        });
    }
};
