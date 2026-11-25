<?php

namespace Database\Seeders;

use App\Models\Clo;
use App\Models\Indicator;
use App\Models\OralRubricLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F13–F16 — master data penilaian dari "Rubrik Capstone Design Project.xlsx" (data/rubrik-capstone.php):
 * resume CLO & bobot per bentuk penilaian, indikator & bobotnya, rubrik dokumen per indikator, dan rubrik lisan.
 * Menyamakan isi basis data dengan berkas tersebut: CLO, indikator, dan level lisan yang tidak ada di berkas dihapus
 * (skor penilaian pada indikator yang dihapus ikut terhapus).
 */
class AssessmentMasterSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__.'/data/rubrik-capstone.php';

        // Seeder ini juga dipanggil migrasi lama, sebelum kolom kode CLO iGadis ada (diisi migrasinya sendiri).
        $igadis = Schema::hasColumn('clos', 'igadis_code');
        $documentTypes = Schema::hasColumn('indicators', 'document_types');

        DB::transaction(function () use ($data, $igadis, $documentTypes) {
            $cloIds = [];
            foreach ($data['clos'] as $code => $row) {
                $clo = Clo::query()->updateOrCreate(['code' => $code], [
                    'description' => $row['description'],
                    'assessment_scheme' => $row['scheme'],
                    'weight' => $row['weight'],
                ] + ($igadis ? ['igadis_code' => $row['igadis'] ?? Clo::IGADIS_DEFAULTS[$code] ?? null] : []));
                $clo->componentWeights()->delete();
                foreach ($row['components'] as $component => $weight) {
                    $clo->componentWeights()->create(compact('component', 'weight'));
                }
                $cloIds[$code] = $clo->id;
            }
            Clo::query()->whereNotIn('code', array_keys($data['clos']))->delete();

            foreach ($data['indicators'] as $code => $row) {
                $indicator = Indicator::query()->updateOrCreate(['code' => $code], [
                    'clo_id' => $cloIds[$row['clo']],
                    'name' => $row['name'],
                    'evidence' => $row['evidence'],
                    'weight' => $row['weight'],
                    'scheme' => $row['scheme'],
                ]);
                $indicator->componentWeights()->delete();
                foreach ($row['components'] as $component => $weight) {
                    $indicator->componentWeights()->create(compact('component', 'weight'));
                }
                // Dokumen terkait = jenis dokumen bentuk penilaian yang memberi bobot (kolom Assessment sheet
                // "Rubrikasi Dokumen v1"); kolomnya baru ada setelah migrasi 2026_11_20_000700.
                if ($documentTypes) {
                    $types = collect($row['components'])->filter(fn ($w) => $w > 0)->keys()
                        ->flatMap(fn ($c) => config("capstone.default_components.{$c}.document_types", []))->unique()
                        ->reject(fn ($t) => in_array($t, \App\Http\Controllers\Admin\RubricController::UNASSESSED_DOCUMENTS, true))->all();
                    $sorted = array_values(array_intersect(array_keys(config('capstone.document_types')), $types));
                    $indicator->update(['document_types' => $sorted ?: null]);
                }
                if (isset($data['rubrics'][$code])) {
                    $indicator->rubric()->updateOrCreate([], $data['rubrics'][$code]);
                }
            }
            Indicator::query()->whereNotIn('code', array_keys($data['indicators']))->delete();

            foreach ($data['oral'] as $level => $row) {
                OralRubricLevel::query()->updateOrCreate(['level' => $level], $row);
            }
            OralRubricLevel::query()->whereNotIn('level', array_keys($data['oral']))->delete();
        });
    }
}
