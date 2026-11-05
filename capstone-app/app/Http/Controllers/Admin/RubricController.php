<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRubric;
use App\Models\Indicator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * F15 — rubrik penilaian dokumen per indikator, ditampilkan sebagai satu tabel (Kode · CLO · Indikator · Bobot ·
 * Poin Penilaian · deskripsi tingkat 5–0 · Contoh Pemenuhan Tingkat 5). Tambah (modal) memilih indikator yang belum
 * berubrik; baris dapat dicentang untuk Edit Pilihan (sekaligus) atau Hapus Pilihan. Semua isian wajib kecuali poin penilaian.
 * Kode, CLO, Indikator, dan Bobot milik data indikator — hanya ditampilkan di sini, diubah lewat menu Indikator.
 */
class RubricController extends Controller
{
    public function index(): View
    {
        $indicators = Indicator::query()->with(['clo', 'rubric'])->orderBy('code')->get();

        return view('admin.master.rubrics', [
            'rubrics' => $indicators->filter(fn (Indicator $i) => $i->rubric)->values(),
            'available' => $indicators->reject(fn (Indicator $i) => $i->rubric)->values(),
            'levels' => config('capstone.score_levels'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['indicator_id' => [
            'required', Rule::exists('indicators', 'id'), Rule::unique('document_rubrics', 'indicator_id'),
        ]] + self::rules(), [
            'indicator_id.required' => 'Pilih indikator.',
            'indicator_id.unique' => 'Indikator ini sudah memiliki rubrik.',
        ]);
        $indicator = Indicator::query()->findOrFail($data['indicator_id']);
        $indicator->rubric()->create(collect($data)->except('indicator_id')->all());

        return redirect()->route('admin.rubrics.index')->with('status', "Rubrik {$indicator->code} ditambahkan.");
    }

    /** Edit Pilihan: simpan beberapa rubrik sekaligus (rows[indicator_id][kolom]); data indikator tidak diubah di sini. */
    public function updateMany(Request $request): RedirectResponse
    {
        $rules = ['rows' => ['required', 'array', 'min:1'], 'rows.*' => ['array']];
        foreach (self::rules() as $field => $rule) {
            $rules["rows.*.{$field}"] = $rule;
        }
        $data = $request->validate($rules, ['rows.required' => 'Pilih minimal satu rubrik.']);

        $rubrics = DocumentRubric::query()->whereIn('indicator_id', array_keys($data['rows']))->get();
        foreach ($rubrics as $rubric) {
            $rubric->update(collect($data['rows'][$rubric->indicator_id])->only(array_keys(self::rules()))->all());
        }

        return redirect()->route('admin.rubrics.index')->with('status', $rubrics->count().' rubrik diperbarui.');
    }

    /** Hapus Pilihan: hapus rubrik indikator yang dicentang (indikatornya tetap ada). */
    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']], ['ids.required' => 'Pilih minimal satu rubrik.']);
        $deleted = DocumentRubric::query()->whereIn('indicator_id', $data['ids'])->delete();

        return redirect()->route('admin.rubrics.index')->with('status', "{$deleted} rubrik dihapus.");
    }

    /** Semua kolom rubrik wajib kecuali poin penilaian. */
    private static function rules(): array
    {
        $rules = [
            'assessment_points' => ['nullable', 'string', 'max:3000'],
            'example_level_5' => ['required', 'string', 'max:3000'],
        ];
        foreach (array_keys(config('capstone.score_levels')) as $level) {
            $rules["level_{$level}"] = ['required', 'string', 'max:3000'];
        }

        return $rules;
    }
}
