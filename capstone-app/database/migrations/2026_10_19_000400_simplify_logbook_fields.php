<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Log bimbingan cukup tanggal + catatan bimbingan; penanggung jawab & luaran menjadi opsional (data lama tetap). */
    public function up(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            $table->string('responsible_person', 150)->nullable()->change();
            $table->text('output')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('logbooks', function (Blueprint $table) {
            $table->string('responsible_person', 150)->nullable(false)->default('')->change();
            $table->text('output')->nullable(false)->change();
        });
    }
};
