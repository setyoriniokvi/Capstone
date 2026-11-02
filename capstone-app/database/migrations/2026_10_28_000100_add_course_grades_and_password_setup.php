<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registrasi akun + tim oleh ketua: indeks nilai mata kuliah prasyarat per mahasiswa (users.course_grades,
     * {kode: nilai}). Tautan atur password anggota memakai Signed URL, jadi tidak perlu tabel token.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('course_grades')->nullable()->after('passed_courses');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('course_grades');
        });
    }
};
