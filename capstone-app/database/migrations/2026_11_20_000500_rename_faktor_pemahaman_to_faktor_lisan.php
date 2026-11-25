<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Istilah "Faktor Pemahaman" diganti "Faktor Lisan" pada baris informasi Cara nilai dihitung (score_rules, semua periode). */
return new class extends Migration
{
    public function up(): void
    {
        $this->swap('Faktor Pemahaman', 'Faktor Lisan');
    }

    public function down(): void
    {
        $this->swap('Faktor Lisan', 'Faktor Pemahaman');
    }

    private function swap(string $from, string $to): void
    {
        foreach (DB::table('score_rules')->get(['id', 'label', 'description']) as $row) {
            DB::table('score_rules')->where('id', $row->id)->update([
                'label' => str_replace($from, $to, (string) $row->label),
                'description' => str_replace([$from, mb_strtolower($from)], [$to, mb_strtolower($to)], (string) $row->description),
            ]);
        }
    }
};
