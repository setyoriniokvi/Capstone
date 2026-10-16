<?php

namespace Database\Seeders;

use App\Models\StudyProgram;
use Illuminate\Database\Seeder;

/** Identifier prodi FRI (Alur §3.1: TI, SI, DSC, MR). */
class StudyProgramSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'TI' => 'S1 Teknik Industri',
            'SI' => 'S1 Sistem Informasi',
            'DSC' => 'S1 Digital Supply Chain',
            'MR' => 'S1 Manajemen Rekayasa',
        ] as $code => $name) {
            StudyProgram::query()->updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
