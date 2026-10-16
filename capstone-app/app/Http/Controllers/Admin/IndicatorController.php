<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssessmentScore;
use App\Models\Clo;
use App\Models\Indicator;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** F14 — master indikator penilaian per CLO. */
class IndicatorController extends Controller
{
    public function index(ScoreCalculator $calculator): View
    {
        return view('admin.master.indicators', [
            'clos' => Clo::query()->with(['indicators.componentWeights', 'indicators.rubric', 'componentWeights'])->orderBy('code')->get(),
            'summary' => $calculator->weightSummary(),
            'components' => config('capstone.components'),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.master.indicator-form', [
            'indicator' => new Indicator(['clo_id' => $request->integer('clo') ?: null, 'scheme' => 'dokumen']),
            'clos' => Clo::query()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save(new Indicator, $request);

        return redirect()->route('admin.indicators.index')->with('status', 'Indikator ditambahkan.');
    }

    public function edit(Indicator $indicator): View
    {
        return view('admin.master.indicator-form', ['indicator' => $indicator->load('componentWeights'), 'clos' => Clo::query()->orderBy('code')->get()]);
    }

    public function update(Request $request, Indicator $indicator): RedirectResponse
    {
        $this->save($indicator, $request);

        return redirect()->route('admin.indicators.index')->with('status', "{$indicator->code} diperbarui.");
    }

    public function destroy(Indicator $indicator): RedirectResponse
    {
        if (AssessmentScore::query()->where('indicator_id', $indicator->id)->exists()) {
            return back()->withErrors(['indicator' => 'Indikator sudah dipakai dalam penilaian dan tidak dapat dihapus.']);
        }
        $indicator->delete();

        return back()->with('status', 'Indikator dihapus.');
    }

    private function save(Indicator $indicator, Request $request): void
    {
        $components = array_keys(config('capstone.components'));
        $data = $request->validate([
            'clo_id' => ['required', Rule::exists('clos', 'id')],
            'code' => ['required', 'string', 'max:20', Rule::unique('indicators', 'code')->ignore($indicator->id)],
            'name' => ['required', 'string', 'max:255'],
            'evidence' => ['nullable', 'string', 'max:255'],
            'scheme' => ['required', Rule::in(array_keys(config('capstone.schemes')))],
            'weights' => ['array'],
            'weights.*' => ['nullable', 'numeric', 'between:0,100'],
        ]);
        $weights = collect($data['weights'] ?? [])->only($components)->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0);

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
