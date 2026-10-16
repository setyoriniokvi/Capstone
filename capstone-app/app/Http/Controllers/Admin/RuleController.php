<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\PrerequisiteCourse;
use App\Models\RegistrationRule;
use App\Models\StudyProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** F5 — aturan registrasi tim per program studi untuk periode aktif. */
class RuleController extends Controller
{
    public function index(): View
    {
        $period = Period::active();
        $rules = $period ? $period->registrationRules()->with('courses')->get()->keyBy('prodi_code') : collect();

        return view('admin.rules', [
            'period' => $period,
            'prodis' => StudyProgram::query()->orderBy('code')->get(),
            'rules' => $rules,
        ]);
    }

    public function update(Request $request, string $prodi): RedirectResponse
    {
        $period = Period::active() ?? abort(409, 'Belum ada periode aktif.');
        abort_unless(StudyProgram::query()->where('code', $prodi)->exists(), 404);

        $data = $request->validate([
            'min_sks' => ['required', 'integer', 'between:0,160'],
            'min_members' => ['required', 'integer', 'between:1,10'],
            'max_members' => ['required', 'integer', 'gte:min_members', 'between:1,10'],
        ]);
        $data['require_distinct_region'] = $request->boolean('require_distinct_region');
        $data['require_distinct_specialization'] = $request->boolean('require_distinct_specialization');

        RegistrationRule::query()->updateOrCreate(['period_id' => $period->id, 'prodi_code' => $prodi], $data);

        return back()->with('status', "Aturan registrasi {$prodi} disimpan.");
    }

    public function addCourse(Request $request, RegistrationRule $rule): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
        ]);
        $rule->courses()->updateOrCreate(['code' => Str::upper(trim($data['code']))], ['name' => $data['name']]);

        return back()->with('status', 'Mata kuliah prasyarat ditambahkan.');
    }

    public function removeCourse(PrerequisiteCourse $course): RedirectResponse
    {
        $course->delete();

        return back()->with('status', 'Mata kuliah prasyarat dihapus.');
    }
}
