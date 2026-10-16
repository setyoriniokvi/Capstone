<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LecturerProfile;
use App\Models\StudyProgram;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** F3 — CRUD data & akun dosen beserta hak aksesnya. */
class LecturerController extends Controller
{
    public function index(Request $request): View
    {
        $lecturers = User::query()->lecturers()->with('lecturerProfile')
            ->when($request->filled('prodi'), fn ($q) => $q->where('prodi_code', $request->string('prodi')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)
                    ->orWhereHas('lecturerProfile', fn ($q) => $q->where('lecturer_code', 'like', $term)));
            })
            ->when($request->filled('akses'), fn ($q) => $q->whereHas('lecturerProfile', fn ($q) => $q->where((string) $request->string('akses'), true)))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('admin.lecturers.index', ['lecturers' => $lecturers, 'prodis' => StudyProgram::query()->orderBy('code')->get()]);
    }

    public function create(): View
    {
        return view('admin.lecturers.form', ['lecturer' => new User(['role' => 'dosen']), 'profile' => new LecturerProfile, 'prodis' => StudyProgram::query()->orderBy('code')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $user = User::create($data['user'] + ['role' => 'dosen']);
            $user->lecturerProfile()->create($data['profile']);
        });

        return redirect()->route('admin.lecturers.index')->with('status', 'Akun dosen dibuat. Dosen dapat masuk dengan akun Google '.$data['user']['email'].'.');
    }

    public function edit(User $lecturer): View
    {
        abort_unless($lecturer->isLecturer(), 404);

        return view('admin.lecturers.form', ['lecturer' => $lecturer, 'profile' => $lecturer->lecturerProfile ?? new LecturerProfile, 'prodis' => StudyProgram::query()->orderBy('code')->get()]);
    }

    public function update(Request $request, User $lecturer): RedirectResponse
    {
        abort_unless($lecturer->isLecturer(), 404);
        $data = $this->validated($request, $lecturer);
        DB::transaction(function () use ($lecturer, $data) {
            $lecturer->update($data['user']);
            $lecturer->lecturerProfile()->updateOrCreate([], $data['profile']);
        });

        return redirect()->route('admin.lecturers.index')->with('status', 'Data dosen diperbarui.');
    }

    public function toggle(User $lecturer): RedirectResponse
    {
        abort_unless($lecturer->isLecturer(), 404);
        $lecturer->update(['is_active' => ! $lecturer->is_active]);

        return back()->with('status', $lecturer->is_active ? 'Akun dosen diaktifkan.' : 'Akun dosen dinonaktifkan.');
    }

    private function validated(Request $request, ?User $lecturer = null): array
    {
        $profileId = $lecturer?->lecturerProfile?->id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($lecturer?->id)],
            'phone' => ['nullable', 'regex:/^\+?[0-9 \-]{8,20}$/'],
            'prodi_code' => ['nullable', Rule::exists('study_programs', 'code')],
            'lecturer_code' => ['required', 'string', 'max:10', Rule::unique('lecturer_profiles', 'lecturer_code')->ignore($profileId)],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('lecturer_profiles', 'nip')->ignore($profileId)],
            'specialization' => ['nullable', 'string', 'max:150'],
        ], ['phone.regex' => 'Nomor WhatsApp hanya berisi angka, spasi, atau tanda +.']);

        return [
            'user' => [
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'prodi_code' => $data['prodi_code'] ?? null,
            ],
            'profile' => [
                'lecturer_code' => Str::upper($data['lecturer_code']),
                'nip' => $data['nip'] ?? null,
                'specialization' => $data['specialization'] ?? null,
                'can_propose_topic' => $request->boolean('can_propose_topic'),
                'can_supervise' => $request->boolean('can_supervise'),
                'can_examine' => $request->boolean('can_examine'),
                'is_class_lecturer' => $request->boolean('is_class_lecturer'),
            ],
        ];
    }
}
