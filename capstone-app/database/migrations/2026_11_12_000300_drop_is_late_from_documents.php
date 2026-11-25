<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F26 — pengumpulan dokumen ditutup setelah deadline (milestone "Overdue"), jadi penanda "terlambat" tidak dipakai lagi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('is_late');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->boolean('is_late')->default(false);
        });
    }
};
