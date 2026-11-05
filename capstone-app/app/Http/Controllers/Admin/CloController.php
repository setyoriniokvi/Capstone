<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clo;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * F13 — master resume penilaian per CLO dalam satu tabel (CLO · Komponen Penilaian · Skema Penilaian · Bobot Penilaian,
 * baris Total di bawah). Kotak centang tiap baris; kanan atas: Hapus Pilihan · Edit Pilihan · Tambah CLO (modal).
 * Bobot CLO = jumlah pembagian bobotnya ke setiap bentuk penilaian (diisi di form tambah / edit).
 */
class CloController extends Controller
{
    public function index(ScoreCalculator $calculator): View
    {
        return view('admin.master.clos', [
            'clos' => $calculator->clos(),
            'summary' => $calculator->weightSummary(),
            'components' => config('capstone.components'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(self::rules('') + [
            'code' => ['required', 'string', 'max:10', Rule::unique('clos', 'code')],
        ]);
        $this->save(new Clo, $data);

        return redirect()->route('admin.clos.index')->with('status', 'CLO '.Str::upper($data['code']).' ditambahkan.');
    }

    /** Edit Pilihan: rows[id][code, description, assessment_scheme, weights[]] disimpan sekaligus. */
    public function updateMany(Request $request): RedirectResponse
    {
        $data = $request->validate(['rows' => ['required', 'array', 'min:1']] + self::rules('rows.*.') + [
            'rows.*.code' => ['required', 'string', 'max:10', 'distinct:ignore_case'],
        ], ['rows.required' => 'Centang CLO terlebih dahulu.', 'rows.*.code.distinct' => 'Kode CLO tidak boleh sama.']);

        $clos = Clo::query()->whereIn('id', array_keys($data['rows']))->get()->keyBy('id');
        validator($data)->after(function (Validator $v) use ($data, $clos) {
            foreach ($data['rows'] as $id => $row) {
                $code = Str::upper($row['code']);
                if (Clo::query()->where('code', $code)->whereNotIn('id', $clos->keys())->exists()) {
                    $v->errors()->add("rows.{$id}.code", "Kode {$code} sudah dipakai CLO lain.");
                }
            }
        })->validate();

        DB::transaction(function () use ($data, $clos) {
            // Kode sementara dulu agar pertukaran kode antarbaris tidak bentrok dengan indeks unik.
            foreach ($clos as $clo) {
                $clo->update(['code' => '~'.$clo->id]);
            }
            foreach ($clos as $id => $clo) {
                $this->save($clo, $data['rows'][$id]);
            }
        });

        return redirect()->route('admin.clos.index')->with('status', $clos->count().' CLO diperbarui.');
    }

    /** Hapus Pilihan: CLO yang masih memiliki indikator tidak dapat dihapus. */
    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']], ['ids.required' => 'Centang CLO terlebih dahulu.']);
        $clos = Clo::query()->withCount('indicators')->whereIn('id', $data['ids'])->get();
        $used = $clos->where('indicators_count', '>', 0);
        if ($used->isNotEmpty()) {
            throw ValidationException::withMessages(['ids' => 'CLO '.$used->pluck('code')->implode(', ').' masih memiliki indikator. Hapus indikatornya terlebih dahulu. Tidak ada yang dihapus.']);
        }
        $clos->each->delete();

        return redirect()->route('admin.clos.index')->with('status', $clos->count().' CLO dihapus.');
    }

    private static function rules(string $prefix): array
    {
        return [
            "{$prefix}description" => ['required', 'string', 'max:2000'],
            "{$prefix}assessment_scheme" => ['nullable', 'string', 'max:150'],
            "{$prefix}weights" => ['array'],
            "{$prefix}weights.*" => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    private function save(Clo $clo, array $data): void
    {
        $weights = collect($data['weights'] ?? [])->only(array_keys(config('capstone.components')))
            ->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0);

        DB::transaction(function () use ($clo, $data, $weights) {
            $clo->fill([
                'code' => Str::upper($data['code']),
                'description' => $data['description'],
                'assessment_scheme' => $data['assessment_scheme'] ?? null,
                'weight' => $weights->sum(), // bobot CLO = jumlah pembagian bobotnya
            ])->save();
            $clo->componentWeights()->delete();
            foreach ($weights as $component => $weight) {
                $clo->componentWeights()->create(compact('component', 'weight'));
            }
        });
    }
}
