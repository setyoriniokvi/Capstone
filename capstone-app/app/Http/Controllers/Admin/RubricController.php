<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Indicator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F15 — rubrik penilaian dokumen per indikator. */
class RubricController extends Controller
{
    public function index(): View
    {
        return view('admin.master.rubrics', [
            'indicators' => Indicator::query()->with(['clo', 'rubric'])->orderBy('code')->get(),
        ]);
    }

    public function edit(Indicator $indicator): View
    {
        return view('admin.master.rubric-form', ['indicator' => $indicator->load('clo', 'rubric')]);
    }

    public function update(Request $request, Indicator $indicator): RedirectResponse
    {
        $rules = ['assessment_points' => ['required', 'string', 'max:3000'], 'example_level_5' => ['nullable', 'string', 'max:3000']];
        foreach (range(0, 5) as $level) {
            $rules["level_{$level}"] = ['required', 'string', 'max:3000'];
        }
        $indicator->rubric()->updateOrCreate([], $request->validate($rules));

        return redirect()->route('admin.rubrics.index')->with('status', "Rubrik {$indicator->code} disimpan.");
    }

    public function destroy(Indicator $indicator): RedirectResponse
    {
        $indicator->rubric()->delete();

        return back()->with('status', 'Rubrik dihapus.');
    }
}
