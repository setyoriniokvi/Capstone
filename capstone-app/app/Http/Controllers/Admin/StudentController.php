<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\User;
use App\Services\ScreeningService;
use App\Services\TeamRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * F4 — data & akun mahasiswa; formulir tambah/ubah berupa modal di halaman daftar. Peminatan & indeks nilai
 * mata kuliah prasyarat dipilih dari aturan periode yang sedang dibuka (sama dengan formulir registrasi tim).
 */
class StudentController extends Controller
{
    public function index(Request $request, TeamRegistration $registration): View
    {
        $period = Period::workspace();
        $students = User::query()->students()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('nim', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->input('tim') === 'belum' && $period, fn ($q) => $q->whereDoesntHave('memberships', fn ($m) => $m->where('invite_status', 'diterima')->whereHas('team', fn ($t) => $t->where('period_id', $period->id))))
            ->with(['memberships' => fn ($m) => $m->where('invite_status', 'diterima')->with('team:id,name,period_id')])
            ->orderBy('nim')
            ->paginate(25)->withQueryString();

        return view('admin.students.index', ['students' => $students, 'period' => $period] + $registration->options($period));
    }

    public function store(Request $request, TeamRegistration $registration): RedirectResponse
    {
        [$data, $grades] = $this->validated($request, $registration);
        (new User($data + ['role' => 'mahasiswa']))->setCourseGrades($grades)->save();

        return redirect()->route('admin.students.index')->with('status', 'Akun mahasiswa dibuat. Mahasiswa dapat masuk dengan email '.$data['email'].'.');
    }

    public function update(Request $request, User $student, TeamRegistration $registration): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        [$data, $grades] = $this->validated($request, $registration, $student);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        $student->fill($data)->setCourseGrades($grades)->save();

        return redirect()->route('admin.students.index')->with('status', 'Data mahasiswa diperbarui.');
    }

    public function toggle(User $student): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $student->update(['is_active' => ! $student->is_active]);

        return back()->with('status', $student->is_active ? 'Akun mahasiswa diaktifkan.' : 'Akun mahasiswa dinonaktifkan.');
    }

    /**
     * Isi/ubah peminatan satu mahasiswa (dari detail tim Registrasi Tim). Peminatan melekat pada akun ber-NIM itu,
     * jadi mahasiswa yang belum punya peminatan saat registrasi tidak perlu mendaftar ulang. Screening tim yang
     * masih menunggu dihitung ulang.
     */
    public function specialization(Request $request, User $student, TeamRegistration $registration, ScreeningService $screening): RedirectResponse
    {
        abort_unless($student->isStudent(), 404);
        $period = Period::workspace();
        $data = $request->validate(['specialization' => ['nullable', 'string', Rule::in($registration->options($period)['specializations']->all())]]);
        $student->update(['specialization' => ($data['specialization'] ?? null) ?: null]);

        $team = $student->teamFor($period);
        if ($team?->status === 'menunggu') {
            $screening->apply($team);
        }

        return back()->with('status', "Peminatan {$student->name} ".($student->specialization ? "diatur ke {$student->specialization}." : 'dikosongkan.'));
    }

    /**
     * Input manual: identitas, kontak, SKS, dan daerah asal wajib (impor Excel/CSV boleh mengosongkan kolom yang belum ada);
     * peminatan & indeks nilai opsional; password wajib saat tambah. Mengembalikan [data akun, indeks nilai per kode MK].
     */
    private function validated(Request $request, TeamRegistration $registration, ?User $student = null): array
    {
        $options = $registration->options(Period::workspace());
        // Peminatan lama yang tidak ada di daftar periode tetap boleh dipertahankan.
        $specializations = $options['specializations']->push($student?->specialization)->filter()->unique()->all();
        $data = $request->validate([
            'nim' => ['required', 'string', 'max:20', Rule::unique('users', 'nim')->ignore($student?->id)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($student?->id)],
            'phone' => ['required', 'regex:/^\+?[0-9 \-]{8,20}$/'],
            'sks_passed' => ['required', 'integer', 'between:0,200'],
            'grades' => ['nullable', 'array'],
            'grades.*' => ['nullable', Rule::in($options['grades'])],
            'origin_region' => ['required', 'string', 'max:100'],
            'specialization' => ['nullable', 'string', Rule::in($specializations)],
            'password' => [$student ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
        ], ['phone.regex' => 'Nomor WhatsApp hanya berisi angka, spasi, atau tanda +.']);
        $data['email'] = Str::lower($data['email']);
        $data['specialization'] = ($data['specialization'] ?? null) ?: null;
        $grades = $options['courses']->mapWithKeys(fn ($c) => [$c->code => $data['grades'][$c->id] ?? null])->filter()->all();
        unset($data['grades']);

        return [$data, $grades];
    }
}
