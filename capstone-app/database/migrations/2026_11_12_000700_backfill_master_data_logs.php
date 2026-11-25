<?php

use App\Services\MasterData\MasterDataLogger;
use Illuminate\Database\Migrations\Migration;

/** Master Data memuat semua data: data yang sudah ada sebelum fitur log dipasang dicatat sebagai "Dibuat" (data awal). */
return new class extends Migration
{
    public function up(): void
    {
        (new MasterDataLogger)->backfill();
    }

    public function down(): void
    {
        // Log tidak dihapus saat rollback.
    }
};
