<?php

namespace Database\Seeders;

use App\Models\Clo;
use App\Models\Indicator;
use App\Models\OralRubricLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($data) {
            $cloIds = [];
            foreach ($data['clos'] as $code => $row) {
                $clo = Clo::query()->updateOrCreate(['code' => $code], [
                    'description' => $row['description'],
                    'assessment_scheme' => $row['scheme'],
                    'weight' => $row['weight'],
                ]);
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
