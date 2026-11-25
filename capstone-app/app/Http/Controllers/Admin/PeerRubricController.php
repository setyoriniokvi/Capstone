<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PeerAspect;
use App\Models\PeerReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * F28 — rubrik peer review: aspek penilaian beserta definisi dan deskripsi tiap level (nilai 0 / 50 / 65 / 75 / 90) dalam satu tabel. Tambah Aspek
 * (modal, semua isian wajib); baris dapat dicentang untuk Edit Pilihan atau Hapus Pilihan. Menghapus aspek ikut
 * menghapus nilai peer review pada aspek itu agar kelengkapan & nilai peer review tetap konsisten.
 */
class PeerRubricController extends Controller
{
    public function index(): View
    {
        return view('admin.master.peer', ['aspects' => PeerAspect::query()->orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(self::rules(), self::messages());
        PeerAspect::create($data + [
            'key' => PeerAspect::uniqueKey($data['name']),
            'sort_order' => (int) PeerAspect::query()->max('sort_order') + 1,
        ]);

        return redirect()->route('admin.peer-rubric.index')->with('status', "Aspek “{$data['name']}” ditambahkan.");
    }

    /** Edit Pilihan: simpan beberapa aspek sekaligus (rows[id][kolom]). */
    public function updateMany(Request $request): RedirectResponse
    {
        $rules = ['rows' => ['required', 'array', 'min:1'], 'rows.*' => ['array']];
        foreach (self::rules() as $field => $rule) {
            $rules["rows.*.{$field}"] = $rule;
        }
        $data = $request->validate($rules, ['rows.required' => 'Pilih minimal satu aspek.']
            + collect(self::messages())->mapWithKeys(fn ($m, $k) => ["rows.*.{$k}" => $m])->all());

        $aspects = PeerAspect::query()->whereIn('id', array_keys($data['rows']))->get();
        foreach ($aspects as $aspect) {
            $aspect->update($data['rows'][$aspect->id]);
        }

        return redirect()->route('admin.peer-rubric.index')->with('status', $aspects->count().' aspek diperbarui.');
    }

    /** Hapus Pilihan (nilai peer review pada aspek tersebut ikut dihapus). */
    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']], ['ids.required' => 'Pilih minimal satu aspek.']);
        $aspects = PeerAspect::query()->whereIn('id', $data['ids'])->get();

        DB::transaction(function () use ($aspects) {
            PeerReview::query()->whereIn('aspect', $aspects->pluck('key'))->delete();
            PeerAspect::query()->whereKey($aspects->modelKeys())->delete();
        });

        return redirect()->route('admin.peer-rubric.index')->with('status', $aspects->count().' aspek dihapus.');
    }

    private static function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'definition' => ['required', 'string', 'max:3000'],
        ];
        foreach (PeerAspect::LEVELS as $level) {
            $rules["level_{$level}"] = ['required', 'string', 'max:3000'];
        }

        return $rules;
    }

    private static function messages(): array
    {
        return collect(PeerAspect::LEVELS)->mapWithKeys(fn ($l) => ["level_{$l}.required" => 'Deskripsi nilai '.PeerAspect::POINTS[$l].' wajib diisi.'])->all()
            + ['name.required' => 'Nama aspek wajib diisi.', 'definition.required' => 'Definisi aspek wajib diisi.'];
    }
}
