<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Master data dari dokumen referensi. Data demo dijalankan terpisah:
     *   php artisan db:seed --class=DemoSeeder
     */
    public function run(): void
    {
        $this->call([
            StudyProgramSeeder::class,
            AssessmentMasterSeeder::class,
        ]);
    }
}
