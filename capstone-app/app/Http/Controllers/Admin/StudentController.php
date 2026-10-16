<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** F4 — data & akun mahasiswa. */
class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $period = Period::active();
        $students = User::query()->students()
            ->when($request->filled('prodi'), fn ($q) => $q->where('prodi_code', $request->string('prodi')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('nim', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->input('tim') === 'belum' && $period, fn ($q) => $q->whereDoesntHave('memberships', fn ($m) => $m->where('invite_status', 'diterima')->whereHas('team', fn ($t) => $t->where('period_id', $period->id))))
            ->with(['memberships' => fn ($m) => $m->where('invite_status', 'diterima')->with('team:id,code,period_id')])
            ->orderBy('nim')
            ->paginate(25)->withQueryString();

        return view('admin.students.index', ['students' => $students, 'prodis' => StudyProgram::query()->orderBy('code')->get(), 'period' => $period]);
    }

    public function edit(User $student): View
    {
        abort_unless($student->isStudent(), 404);

        return view('admin.students.form', ['student' => $student, 'prodis' => StudyProgram::query()->orderBy('code')->get()]);
    }

    public function update(Request $request, User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $data = $request->validate([
            'nim' => ['required', 'string', 'max:20', Rule::unique('users', 'nim')->ignore($student->id)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($student->id)],
            'prodi_code' => ['required', Rule::exists('study_programs', 'code')],
            'phone' => ['nullable', 'regex:/^\+?[0-9 \-]{8,20}$/'],
            'sks_passed' => ['nullable', 'integer', 'between:0,200'],
            'passed_courses' => ['nullable', 'string', 'max:2000'],
            'origin_region' => ['nullable', 'string', 'max:100'],
            'specialization' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ]);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $data['email'] = Str::lower($data['email']);
        $data['passed_courses'] = filled($data['passed_courses'] ?? null)
            ? array_values(array_filter(array_map(fn ($c) => Str::upper(trim($c)), preg_split('/[;,|\n]/', $data['passed_courses']))))
            : null;

        $student->update($data);

        return redirect()->route('admin.students.index')->with('status', 'Data mahasiswa diperbarui.');
    }

    public function toggle(User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $student->update(['is_active' => ! $student->is_active]);

        return back()->with('status', $student->is_active ? 'Akun mahasiswa diaktifkan.' : 'Akun mahasiswa dinonaktifkan.');
    }
}
