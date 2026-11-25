<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentRubric;
use App\Models\Indicator;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\PeriodContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * F15 — rubrik penilaian dokumen per indikator, ditampilkan sebagai satu tabel (Kode · CLO · Indikator · Bobot ·
 * Poin Penilaian · deskripsi tingkat 5–0). Tambah (modal) memilih indikator yang belum
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
            'documentTypes' => self::documentTypes(),
        ]);
    }

    /**
     * Pilihan "Dokumen terkait": jenis dokumen milestone di pengaturan periode yang sedang dibuka
     * (milestone berjenis Dokumen), berlabel dari config capstone.document_types.
     *
     * @return array<string, string>
     */
    /** Jenis dokumen yang tidak dinilai lewat rubrik dokumen (tidak ditawarkan sebagai Dokumen terkait). */
    public const UNASSESSED_DOCUMENTS = ['materi_presentasi', 'poster'];

    public static function documentTypes(): array
    {
        $period = Period::workspace();
        $types = $period ? $period->milestones()->where('kind', 'dokumen')->whereNotNull('document_type')->pluck('document_type')->unique()
            ->reject(fn ($t) => in_array($t, self::UNASSESSED_DOCUMENTS, true)) : collect();

        // Urutan standar config (Proposal → Laporan Progress → …), bukan urutan milestone
        return collect(config('capstone.document_types'))->only($types->all())
            ->union($types->diff(array_keys(config('capstone.document_types')))->mapWithKeys(fn ($t) => [$t => $t]))->all();
    }

    /** Dokumen terkait (boleh lebih dari satu): jenis dokumen periode ini, atau nilai lama indikator (tetap boleh disimpan). */
    private static function documentRules(string $field, array $current = []): array
    {
        return [$field => ['nullable', 'array'], "{$field}.*" => ['distinct', Rule::in(array_values(array_unique([...array_keys(self::documentTypes()), ...$current])))]];
    }

    /** Urutkan sesuai config capstone.document_types; kosong → null. */
    private static function cleanTypes(?array $types): ?array
    {
        $types = array_values(array_intersect(array_keys(config('capstone.document_types')), $types ?? []))
            ?: array_values(array_unique($types ?? []));

        return $types ?: null;
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['indicator_id' => [
            'required', Rule::exists('indicators', 'id')->where('period_id', PeriodContext::id()), Rule::unique('document_rubrics', 'indicator_id'),
        ]] + self::documentRules('document_types') + self::rules(), [
            'indicator_id.required' => 'Pilih indikator.',
            'indicator_id.unique' => 'Indikator ini sudah memiliki rubrik.',
        ]);
        if ($gaps = self::gapErrors($data)) {
            throw ValidationException::withMessages($gaps);
        }
        $indicator = Indicator::query()->findOrFail($data['indicator_id']);
        $indicator->rubric()->create(collect($data)->except(['indicator_id', 'document_types'])->all());
        $indicator->update(['document_types' => self::cleanTypes($data['document_types'] ?? null)]);

        return redirect()->route('admin.rubrics.index')->with('status', "Rubrik {$indicator->code} ditambahkan.");
    }

    /** Edit Pilihan: simpan beberapa rubrik sekaligus (rows[indicator_id][kolom]); data indikator tidak diubah di sini. */
    public function updateMany(Request $request): RedirectResponse
    {
        $rules = ['rows' => ['required', 'array', 'min:1'], 'rows.*' => ['array']];
        foreach (self::rules() as $field => $rule) {
            $rules["rows.*.{$field}"] = $rule;
        }
        $currentTypes = Indicator::query()->whereIn('id', array_keys((array) $request->input('rows', [])))->get()->mapWithKeys(fn ($i) => [$i->id => $i->document_types ?? []]);
        foreach (array_keys((array) $request->input('rows', [])) as $id) {
            $rules += self::documentRules("rows.{$id}.document_types", $currentTypes[$id] ?? []);
        }
        $data = $request->validate($rules, ['rows.required' => 'Pilih minimal satu rubrik.']);
        $gaps = collect($data['rows'])->flatMap(fn ($row, $id) => self::gapErrors($row, "rows.{$id}."))->all();
        if ($gaps) {
            throw ValidationException::withMessages($gaps);
        }

        $rubrics = DocumentRubric::query()->whereIn('indicator_id', array_keys($data['rows']))->whereIn('indicator_id', Indicator::query()->select('id'))->get();
        foreach ($rubrics as $rubric) {
            $rubric->update(collect($data['rows'][$rubric->indicator_id])->only(array_keys(self::rules()))->all());
            $rubric->indicator->update(['document_types' => self::cleanTypes($data['rows'][$rubric->indicator_id]['document_types'] ?? null)]);
        }

        return redirect()->route('admin.rubrics.index')->with('status', $rubrics->count().' rubrik diperbarui.');
    }

    /** Hapus Pilihan: hapus rubrik indikator yang dicentang (indikatornya tetap ada). */
    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']], ['ids.required' => 'Pilih minimal satu rubrik.']);
        $deleted = DocumentRubric::query()->whereIn('indicator_id', $data['ids'])->whereIn('indicator_id', Indicator::query()->select('id'))->delete();

        return redirect()->route('admin.rubrics.index')->with('status', "{$deleted} rubrik dihapus.");
    }

    /**
     * Semua tingkat 5 … 0 wajib berisi deskripsi (interval maksimum indikator = 5).
     */
    private static function rules(): array
    {
        $rules = [
            'assessment_points' => ['nullable', 'string', 'max:3000'],
        ];
        foreach (array_keys(config('capstone.score_levels')) as $level) {
            // Semua tingkat (5 … 0) wajib berisi deskripsi
            $rules["level_{$level}"] = ['required', 'string', 'max:3000'];
        }

        return $rules;
    }

    /** Tingkat terisi harus berurutan: tingkat k terisi → tingkat k−1 juga terisi. */
    private static function gapErrors(array $values, string $prefix = ''): array
    {
        $errors = [];
        for ($level = 5; $level >= 2; $level--) {
            if (filled($values["level_{$level}"] ?? null) && blank($values['level_'.($level - 1)] ?? null)) {
                $errors[$prefix.'level_'.($level - 1)] = 'Isi tingkat '.($level - 1).' karena tingkat '.$level.' terisi (poin rubrik harus berurutan).';
            }
        }

        return $errors;
    }
}
