<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentComponent;
use App\Models\AssessmentScore;
use App\Models\Clo;
use App\Models\Indicator;
use App\Models\Period;
use App\Services\ScoreCalculator;
use App\Support\PeriodContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * F14 — master indikator penilaian dalam satu tabel (seperti lembar "Indikator"): CLO · ID · Indikator · Bukti/Bab · % ·
 * Skema · bobot per bentuk penilaian, dengan baris total per bentuk penilaian. Kotak centang tiap baris memilih indikator;
 * kotak centang di judul kolom bentuk penilaian memilih bentuk penilaian itu. "Tambah Indikator" (modal), "Edit Pilihan"
 * (indikator & bentuk penilaian terpilih sekaligus), "Hapus Pilihan". Bobot indikator = jumlah bobot per bentuk penilaian.
 */
class IndicatorController extends Controller
{
    public function index(ScoreCalculator $calculator): View
    {
        return view('admin.master.indicators', [
            'indicators' => Indicator::query()->with(['clo', 'componentWeights'])->get()
                ->sortBy(fn (Indicator $i) => $i->clo?->code.'|'.$i->code, SORT_NATURAL)->values(),
            'clos' => Clo::query()->orderBy('code')->get(),
            'componentModels' => AssessmentComponent::query()->orderBy('sort_order')->orderBy('id')->get()->keyBy('key'),
            'summary' => $calculator->weightSummary(),
            'components' => config('capstone.components'),
            'formPeriod' => Period::workspace(),
        ]);
    }

    /**
     * Rumus nilai indikator per bentuk penilaian (dinilai dosen) pada periode ini, disusun dari pilihan baku:
     * pembagi interval (interval maksimum / angka tetap), kali bobot, kali faktor lisan, pengali tambahan.
     * Nilai yang sudah diisi dosen langsung dihitung ulang dengan rumus baru (rekap & hasil penilaian).
     */
    public function formulas(Request $request): RedirectResponse
    {
        $components = AssessmentComponent::query()->get()->reject->isPeerReview()->keyBy('key');
        $data = $request->validate([
            'formula' => ['required', 'array'],
            'formula.*.divisor' => ['required', 'in:level,max,fixed'],
            'formula.*.fixed' => ['nullable', 'required_if:formula.*.divisor,fixed', 'integer', 'between:1,100'],
            'formula.*.multiplier' => ['required', 'numeric', 'gt:0', 'max:100'],
        ], [
            'formula.*.fixed.required_if' => 'Isi angka pembagi tetap.',
            'formula.*.fixed.between' => 'Angka pembagi 1–100.',
            'formula.*.multiplier.gt' => 'Pengali harus lebih dari 0.',
            'formula.*.multiplier.required' => 'Isi pengali tambahan (1 = tanpa pengali).',
        ]);

        foreach ($data['formula'] as $key => $row) {
            $component = $components->get($key);
            if (! $component) {
                continue;
            }
            $component->update(['formula' => AssessmentComponent::normalizeFormula([
                'divisor' => $row['divisor'],
                // Isian angka tetap nonaktif (tidak terkirim) bila pembagi bukan angka tetap → pertahankan angka sebelumnya
                'fixed' => $row['fixed'] ?? AssessmentComponent::normalizeFormula($component->formula)['fixed'],
                'weight' => $request->boolean("formula.{$key}.weight"),
                'oral' => $request->boolean("formula.{$key}.oral"),
                'multiplier' => (float) str_replace(',', '.', (string) $row['multiplier']),
            ])]);
        }
        AssessmentComponent::syncConfig();

        return back()->with('status', 'Rumus nilai per bentuk penilaian disimpan. Nilai yang sudah diisi dosen dihitung ulang dengan rumus baru.');
    }

    /** Tampilan form penilaian dosen periode yang sedang dikelola: split (skenario 2, bawaan) / sequential (skenario 1). */
    public function formMode(Request $request): RedirectResponse
    {
        $data = $request->validate(['assessment_form_mode' => ['required', Rule::in(array_keys(Period::FORM_MODES))]]);
        $period = Period::workspace();
        abort_unless($period, 404);
        $period->update($data);

        return back()->with('status', 'Tampilan form penilaian dosen: '.Period::FORM_MODES[$data['assessment_form_mode']].'.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(self::indicatorRules('') + [
            'code' => ['required', 'string', 'max:20', Rule::unique('indicators', 'code')->where('period_id', PeriodContext::id())],
        ]);
        $this->saveIndicator(new Indicator, $data);

        return redirect()->route('admin.indicators.index')->with('status', 'Indikator '.Str::upper($data['code']).' ditambahkan.');
    }

    /** Edit Pilihan: indikator terpilih (rows[id][…]) dan bentuk penilaian terpilih (components[key][label, roles]). */
    public function updateMany(Request $request): RedirectResponse
    {
        $rules = [
            'rows' => ['array'],
            'rows.*.code' => ['required', 'string', 'max:20', 'distinct:ignore_case'],
            'components' => ['array'],
            'components.*.label' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'components.*.roles' => ['required', 'array', 'min:1'],
            'components.*.roles.*' => ['distinct', Rule::in(array_keys(AssessmentComponent::LECTURER_ROLES))],
        ];
        foreach (self::indicatorRules('rows.*.') as $field => $rule) {
            $rules[$field] = $rule;
        }
        $data = $request->validate($rules, [
            'rows.*.code.distinct' => 'ID indikator tidak boleh sama.',
            'components.*.label.distinct' => 'Nama bentuk penilaian tidak boleh sama.',
            'components.*.roles.required' => 'Pilih minimal satu penilai.',
        ]);
        $rows = $data['rows'] ?? [];
        $componentRows = $data['components'] ?? [];
        if ($rows === [] && $componentRows === []) {
            throw ValidationException::withMessages(['selection' => 'Centang indikator atau bentuk penilaian terlebih dahulu.']);
        }

        $indicators = Indicator::query()->whereIn('id', array_keys($rows))->get()->keyBy('id');
        $components = AssessmentComponent::query()->whereIn('key', array_keys($componentRows))->get()->keyBy('key');
        validator($data)->after(function (Validator $v) use ($rows, $componentRows, $indicators, $components) {
            foreach ($rows as $id => $row) {
                $code = Str::upper($row['code']);
                if (Indicator::query()->where('code', $code)->whereNotIn('id', $indicators->keys())->exists()) {
                    $v->errors()->add("rows.{$id}.code", "ID {$code} sudah dipakai indikator lain.");
                }
            }
            foreach ($componentRows as $key => $row) {
                $component = $components->get($key);
                if (! $component || $component->isPeerReview()) {
                    $v->errors()->add("components.{$key}.label", 'Peer review (diisi mahasiswa) tidak dapat diubah.');
                } elseif (AssessmentComponent::query()->where('label', $row['label'])->whereNotIn('key', $components->keys())->exists()) {
                    $v->errors()->add("components.{$key}.label", "Bentuk penilaian {$row['label']} sudah ada.");
                }
            }
        })->validate();

        DB::transaction(function () use ($rows, $componentRows, $indicators, $components) {
            // Kode sementara dulu agar pertukaran ID antarbaris tidak bentrok dengan indeks unik.
            foreach ($indicators as $indicator) {
                $indicator->update(['code' => '~'.$indicator->id]);
            }
            foreach ($indicators as $id => $indicator) {
                $this->saveIndicator($indicator, $rows[$id]);
            }
            foreach ($components as $key => $component) {
                $component->update([
                    'label' => $componentRows[$key]['label'],
                    'roles' => AssessmentComponent::orderedLecturerRoles($componentRows[$key]['roles']),
                ]);
            }
        });
        AssessmentComponent::syncConfig();

        return redirect()->route('admin.indicators.index')->with('status', collect([
            $indicators->isNotEmpty() ? $indicators->count().' indikator' : null,
            $components->isNotEmpty() ? $components->count().' bentuk penilaian' : null,
        ])->filter()->implode(' dan ').' diperbarui.');
    }

    /** Hapus Pilihan: indikator (beserta rubriknya) dan bentuk penilaian yang dicentang. */
    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['array'], 'ids.*' => ['integer'],
            'components' => ['array'], 'components.*' => ['string'],
        ]);
        $indicators = Indicator::query()->whereIn('id', $data['ids'] ?? [])->get();
        $components = AssessmentComponent::query()->whereIn('key', $data['components'] ?? [])->get();
        if ($indicators->isEmpty() && $components->isEmpty()) {
            throw ValidationException::withMessages(['selection' => 'Centang indikator atau bentuk penilaian terlebih dahulu.']);
        }

        $scoredIds = AssessmentScore::query()->whereIn('indicator_id', $indicators->modelKeys())->distinct()->pluck('indicator_id');
        $used = $indicators->filter(fn (Indicator $i) => $scoredIds->contains($i->id));
        $blocked = $components->filter(fn (AssessmentComponent $c) => $c->isPeerReview() || $c->hasAssessments());
        if ($used->isNotEmpty() || $blocked->isNotEmpty()) {
            throw ValidationException::withMessages(['selection' => collect([
                $used->isNotEmpty() ? 'Indikator '.$used->pluck('code')->implode(', ').' sudah dipakai dalam penilaian.' : null,
                $blocked->isNotEmpty() ? 'Bentuk penilaian '.$blocked->pluck('label')->implode(', ').' sudah memiliki nilai atau diisi mahasiswa.' : null,
            ])->filter()->implode(' ').' Tidak ada yang dihapus.']);
        }

        DB::transaction(function () use ($indicators, $components) {
            $indicators->each->delete();
            $components->each->deleteWithWeights();
            // Bobot indikator = jumlah bobot bentuk penilaian yang tersisa.
            foreach (Indicator::query()->with('componentWeights')->get() as $indicator) {
                $indicator->update(['weight' => $indicator->componentWeights->sum('weight')]);
            }
        });
        AssessmentComponent::syncConfig();

        return redirect()->route('admin.indicators.index')->with('status', collect([
            $indicators->isNotEmpty() ? $indicators->count().' indikator' : null,
            $components->isNotEmpty() ? $components->count().' bentuk penilaian' : null,
        ])->filter()->implode(' dan ').' dihapus.');
    }

    /** Aturan isian indikator; $prefix "" untuk tambah, "rows.*." untuk Edit Pilihan. */
    private static function indicatorRules(string $prefix): array
    {
        return [
            "{$prefix}clo_id" => ['required', Rule::exists('clos', 'id')->where('period_id', PeriodContext::id())],
            "{$prefix}name" => ['required', 'string', 'max:255'],
            "{$prefix}evidence" => ['nullable', 'string', 'max:255'],
            "{$prefix}scheme" => ['required', Rule::in(array_keys(config('capstone.schemes')))],
            "{$prefix}weights" => ['array'],
            "{$prefix}weights.*" => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    private function saveIndicator(Indicator $indicator, array $data): void
    {
        $weights = collect($data['weights'] ?? [])->only(array_keys(config('capstone.components')))
            ->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0);

        DB::transaction(function () use ($indicator, $data, $weights) {
            $indicator->fill([
                'clo_id' => $data['clo_id'],
                'code' => Str::upper($data['code']),
                'name' => $data['name'],
                'evidence' => $data['evidence'] ?? null,
                'scheme' => $data['scheme'],
                'weight' => $weights->sum(),
            ])->save();
            $indicator->componentWeights()->delete();
            foreach ($weights as $component => $weight) {
                $indicator->componentWeights()->create(compact('component', 'weight'));
            }
        });
    }
}
