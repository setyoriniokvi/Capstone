<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tim yang ditolak admin dibubarkan: keanggotaan, KHS, dan akun yang dibuat lewat registrasi dihapus
     * (team_members.account_created), sedangkan data pendaftarnya disimpan sebagai log di teams.archived_members.
     */
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->boolean('account_created')->default(false)->after('is_leader');
        });
        Schema::table('teams', function (Blueprint $table) {
            $table->json('archived_members')->nullable()->after('screening_score');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('archived_members');
        });
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn('account_created');
        });
    }
};
