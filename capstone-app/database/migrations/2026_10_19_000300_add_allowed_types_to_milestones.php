<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tipe file yang boleh diunggah per milestone dokumen (diatur admin); null = bawaan config. */
    public function up(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->json('allowed_types')->nullable()->after('document_type');
        });
    }

    public function down(): void
    {
        Schema::table('milestones', fn (Blueprint $table) => $table->dropColumn('allowed_types'));
    }
};
