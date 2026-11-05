<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OralRubricLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * F16 — rubrik penilaian lisan (level 1–5, faktor pemahaman 0,25–1,00) dalam satu tabel: Level · Faktor · Sebutan ·
 * Perilaku teramati. Tambah (modal, semua isian wajib); baris dapat dicentang untuk Edit Pilihan atau Hapus Pilihan.
 */
class OralRubricController extends Controller
{
    public function index(): View
    {
        return view('admin.master.oral', ['levels' => OralRubricLevel::query()->orderByDesc('level')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'level' => ['required', 'integer', 'between:1,5', Rule::unique('oral_rubric_levels', 'level')],
        ] + self::rules(), self::messages());
        OralRubricLevel::create($data);

        return redirect()->route('admin.oral.index')->with('status', "Level {$data['level']} ditambahkan.");
    }

    /** Edit Pilihan: simpan beberapa level sekaligus (rows[id][kolom]); nomor level tetap unik. */
    public function updateMany(Request $request): RedirectResponse
    {
        $rules = ['rows' => ['required', 'array', 'min:1'], 'rows.*' => ['array'], 'rows.*.level' => ['required', 'integer', 'between:1,5', 'distinct']];
        foreach (self::rules() as $field => $rule) {
            $rules["rows.*.{$field}"] = $rule;
        }
        $data = $request->validate($rules, ['rows.required' => 'Pilih minimal satu level.', 'rows.*.level.distinct' => 'Nomor level tidak boleh sama.']
            + collect(self::messages())->mapWithKeys(fn ($m, $k) => ["rows.*.{$k}" => $m])->all());

        $levels = OralRubricLevel::query()->whereIn('id', array_keys($data['rows']))->get();
        validator($data)->after(function (Validator $v) use ($data, $levels) {
            $taken = OralRubricLevel::query()->whereNotIn('id', $levels->pluck('id'))->pluck('level')->all();
            foreach ($data['rows'] as $id => $row) {
                if (in_array((int) $row['level'], $taken, true)) {
                    $v->errors()->add("rows.{$id}.level", "Level {$row['level']} sudah dipakai.");
                }
            }
        })->validate();

        foreach ($levels as $level) {
            $level->update($data['rows'][$level->id]);
        }

        return redirect()->route('admin.oral.index')->with('status', $levels->count().' level diperbarui.');
    }

    /** Hapus Pilihan. */
    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']], ['ids.required' => 'Pilih minimal satu level.']);
        $deleted = OralRubricLevel::query()->whereIn('id', $data['ids'])->delete();

        return redirect()->route('admin.oral.index')->with('status', "{$deleted} level dihapus.");
    }

    private static function rules(): array
    {
        return [
            'factor' => ['required', 'numeric', 'between:0.25,1'],
            'label' => ['required', 'string', 'max:60'],
            'behavior' => ['required', 'string', 'max:3000'],
        ];
    }

    private static function messages(): array
    {
        return ['factor.between' => 'Faktor pemahaman berada pada rentang 0,25–1,00.', 'level.unique' => 'Level ini sudah ada.'];
    }
}
