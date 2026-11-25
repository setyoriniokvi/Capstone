<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F23 — detail topik/objek (opsional) pada topik mandiri; tautan di dalamnya ditampilkan dapat diklik. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->text('object_detail')->nullable()->after('object');
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('object_detail');
        });
    }
};
