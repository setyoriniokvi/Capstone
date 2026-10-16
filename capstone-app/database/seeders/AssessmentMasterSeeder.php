<?php

namespace Database\Seeders;

use App\Models\Clo;
use App\Models\OralRubricLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * F13 — resume CLO & bobot per bentuk penilaian.
 * Bobot diambil dari Tabel Bobot Penilaian (PRD §8.1 / Alur Tabel 3.2).
 * Kolom "Laporan Akhir" + "Presentasi Akhir" dipetakan ke Sidang Capstone (Pembimbing / Penguji) sesuai F13.
 * Deskripsi CLO diambil dari kolom Kriteria pada Rubrik Penilaian Capstone (Alur Tabel 3.3).
 */
class AssessmentMasterSeeder extends Seeder
{
    public function run(): void
    {
        $clos = [
            'CLO1' => ['Complex Engineering Problem / Complex Computing Problem; open ended solutions.', 'Proposal (minggu 2), Laporan Akhir (minggu 15)', ['proposal' => 5, 'sidang_pembimbing' => 6, 'sidang_penguji' => 4]],
            'CLO2' => ['Body of knowledge.', 'Proposal, Laporan Akhir (minggu 15)', ['proposal' => 10, 'sidang_pembimbing' => 6, 'sidang_penguji' => 4]],
            'CLO3' => ['Verifikasi dan validasi hasil rancangan sistem.', 'Laporan Progress (minggu 11), Laporan Akhir (minggu 15)', ['laporan_progress' => 5, 'sidang_pembimbing' => 3, 'sidang_penguji' => 3]],
            'CLO4' => ['Pemanfaatan teknologi informasi pada solusi yang diusulkan.', 'Laporan Progress (minggu 11), Laporan Akhir (minggu 15)', ['laporan_progress' => 5, 'sidang_pembimbing' => 6, 'sidang_penguji' => 3]],
            'CLO5' => ['Kerjasama dan pembagian pekerjaan dalam tim.', 'Peer Review Mahasiswa', ['peer_review' => 10]],
            'CLO6' => ['Ketepatan penulisan laporan berdasarkan panduan; kelancaran dan kualitas presentasi; kualitas dalam melakukan pameran.', 'Laporan Akhir, Presentasi (minggu 15), Pameran', ['sidang_pembimbing' => 6, 'sidang_penguji' => 4, 'pameran' => 5]],
            'CLO7' => ['Kelengkapan rencana kerja dan logbook; komitmen terhadap tugas.', 'Laporan Akhir (minggu 15)', ['sidang_pembimbing' => 6, 'sidang_penguji' => 4]],
            'CLO8' => ['Mempelajari secara mandiri teori / informasi / model / kerangka standar / metode / perangkat yang belum dipelajari untuk mendukung project capstone design.', 'Laporan Akhir (minggu 15)', ['sidang_pembimbing' => 5]],
        ];

        DB::transaction(function () use ($clos) {
            foreach ($clos as $code => [$description, $scheme, $weights]) {
                $clo = Clo::query()->updateOrCreate(['code' => $code], [
                    'description' => $description,
                    'assessment_scheme' => $scheme,
                    'weight' => array_sum($weights),
                ]);
                $clo->componentWeights()->delete();
                foreach ($weights as $component => $weight) {
                    $clo->componentWeights()->create(compact('component', 'weight'));
                }
            }

            // F16 — level & sebutan sesuai FR. FR hanya menyebut rentang faktor 0,25–1,00;
            // nilai antara dibagi rata dan perilaku teramati PERLU DIISI admin sesuai rubrik resmi.
            $levels = [1 => 'Tidak Mengenali', 2 => 'Hafal Permukaan', 3 => 'Memadai', 4 => 'Menguasai', 5 => 'Menguasai Penuh'];
            foreach ($levels as $level => $label) {
                OralRubricLevel::query()->firstOrCreate(['level' => $level], [
                    'label' => $label,
                    'factor' => round(0.25 + ($level - 1) * 0.1875, 2),
                    'behavior' => 'Perlu diisi: perilaku yang teramati saat verifikasi lisan.',
                ]);
            }
        });
    }
}
