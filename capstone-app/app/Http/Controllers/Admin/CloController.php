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
use Illuminate\View\View;

/** F13 — master resume penilaian per CLO. */
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
        $this->save(new Clo, $request);

        return back()->with('status', 'CLO ditambahkan.');
    }

    public function update(Request $request, Clo $clo): RedirectResponse
    {
        $this->save($clo, $request);

        return back()->with('status', "{$clo->code} diperbarui.");
    }

    public function destroy(Clo $clo): RedirectResponse
    {
        if ($clo->indicators()->exists()) {
            return back()->withErrors(['clo' => 'Hapus indikator milik CLO ini terlebih dahulu.']);
        }
        $clo->delete();

        return back()->with('status', 'CLO dihapus.');
    }

    private function save(Clo $clo, Request $request): void
    {
        $components = array_keys(config('capstone.components'));
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('clos', 'code')->ignore($clo->id)],
            'description' => ['required', 'string', 'max:2000'],
            'assessment_scheme' => ['nullable', 'string', 'max:150'],
            'weights' => ['array'],
            'weights.*' => ['nullable', 'numeric', 'between:0,100'],
        ]);

        $weights = collect($data['weights'] ?? [])->only($components)->map(fn ($v) => (float) $v)->filter(fn ($v) => $v > 0);

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
