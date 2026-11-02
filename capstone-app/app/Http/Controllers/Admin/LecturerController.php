<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LecturerProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * F3 — CRUD data & akun dosen; formulir tambah/ubah berupa modal di halaman daftar. Hak akses: semua dosen "Dosen";
 * Pembimbing / Penguji tercentang otomatis saat diplot dan dapat diubah admin lewat centang di formulir.
 */
class LecturerController extends Controller
{
    public function index(Request $request): View
    {
        $filter = (string) $request->query('akses');
        $lecturers = User::query()->lecturers()->with('lecturerProfile')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)
                    ->orWhereHas('lecturerProfile', fn ($q) => $q->where('lecturer_code', 'like', $term)));
            })
            ->when(in_array($filter, LecturerProfile::ROLES, true), fn ($q) => $q->whereHas('lecturerProfile', fn ($p) => $p->where($filter, true)))
            ->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('admin.lecturers.index', ['lecturers' => $lecturers]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $user = User::create($data['user'] + ['role' => 'dosen', 'password' => $data['password']]);
            $user->lecturerProfile()->create($data['profile']);
        });

        return redirect()->route('admin.lecturers.index')->with('status', 'Akun dosen dibuat. Dosen dapat masuk dengan email '.$data['user']['email'].'.');
    }

    public function update(Request $request, User $lecturer): RedirectResponse
    {
        abort_unless($lecturer->isLecturer(), 404);
        $data = $this->validated($request, $lecturer);
        DB::transaction(function () use ($lecturer, $data) {
            $lecturer->update($data['user'] + array_filter(['password' => $data['password']]));
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

    /** Input manual: semua isian wajib (impor Excel/CSV boleh mengosongkan kolom yang belum ada); password wajib saat tambah. */
    private function validated(Request $request, ?User $lecturer = null): array
    {
        $profileId = $lecturer?->lecturerProfile?->id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($lecturer?->id)],
            'phone' => ['required', 'regex:/^\+?[0-9 \-]{8,20}$/'],
            'lecturer_code' => ['required', 'string', 'max:10', Rule::unique('lecturer_profiles', 'lecturer_code')->ignore($profileId)],
            'nip' => ['required', 'string', 'max:30', Rule::unique('lecturer_profiles', 'nip')->ignore($profileId)],
            'specialization' => ['required', 'string', 'max:150'],
            'password' => [$lecturer ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
        ], ['phone.regex' => 'Nomor WhatsApp hanya berisi angka, spasi, atau tanda +.']);

        return [
            'password' => $data['password'] ?? null,
            'user' => [
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'],
            ],
            'profile' => [
                'lecturer_code' => Str::upper($data['lecturer_code']),
                'nip' => $data['nip'],
                'specialization' => $data['specialization'],
                'can_supervise' => $request->boolean('can_supervise'),
                'can_examine' => $request->boolean('can_examine'),
            ],
        ];
    }
}
