<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OralRubricLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** F16 — rubrik penilaian lisan (level 1–5, faktor pemahaman 0,25–1,00). */
class OralRubricController extends Controller
{
    public function index(): View
    {
        return view('admin.master.oral', ['levels' => OralRubricLevel::query()->orderBy('level')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        OralRubricLevel::create($this->validated($request));

        return back()->with('status', 'Level rubrik lisan ditambahkan.');
    }

    public function update(Request $request, OralRubricLevel $level): RedirectResponse
    {
        $level->update($this->validated($request, $level));

        return back()->with('status', "Level {$level->level} diperbarui.");
    }

    public function destroy(OralRubricLevel $level): RedirectResponse
    {
        $level->delete();

        return back()->with('status', 'Level dihapus.');
    }

    private function validated(Request $request, ?OralRubricLevel $level = null): array
    {
        return $request->validate([
            'level' => ['required', 'integer', 'between:1,5', Rule::unique('oral_rubric_levels', 'level')->ignore($level?->id)],
            'factor' => ['required', 'numeric', 'between:0.25,1'],
            'label' => ['required', 'string', 'max:60'],
            'behavior' => ['required', 'string', 'max:3000'],
        ], ['factor.between' => 'Faktor pemahaman berada pada rentang 0,25–1,00.']);
    }
}
