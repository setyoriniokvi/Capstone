<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\User;
use App\Services\ScreeningService;
use App\Services\TeamRegistration;
use App\Support\Sorting;
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
        // Mahasiswa periode yang sedang dibuka (registrasi, impor, atau input admin di periode ini).
        $query = User::query()->students()->inPeriod($period)->withPeriodStatus()
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('nim', 'like', $term)->orWhere('email', 'like', $term)->orWhere('class_name', 'like', $term));
            })
            ->when($request->input('tim') === 'belum' && $period, fn ($q) => $q->whereDoesntHave('memberships', fn ($m) => $m->where('invite_status', 'diterima')->whereHas('team', fn ($t) => $t->where('period_id', $period->id))))
            ->with(['memberships' => fn ($m) => $m->where('invite_status', 'diterima')->with('team:id,name,period_id')]);
        // Data tetap: bawaan kolom paling kiri (NIM) naik
        Sorting::apply($query, $request, ['nim' => 'nim', 'nama' => 'name', 'kelas' => 'class_name', 'terbaru' => 'created_at', 'kategori' => 'specialization'], 'nim');
        $students = $query->paginate(25)->withQueryString();

        return view('admin.students.index', ['students' => $students, 'period' => $period] + $registration->options($period));
    }

    public function store(Request $request, TeamRegistration $registration): RedirectResponse
    {
        // Mahasiswa yang sudah punya akun (terdaftar di periode lain) cukup ditambahkan ke periode ini.
        $existing = User::query()->students()->where('email', Str::lower(trim((string) $request->input('email'))))->first();
        if ($existing) {
            if ($existing->periods()->whereKey(Period::workspace()?->id)->exists()) {
                return back()->withErrors(['email' => 'Mahasiswa dengan email ini sudah terdaftar di periode ini.'])->withInput();
            }
            $existing->joinPeriod(Period::workspace(), true);
            $existing->syncActiveFromPeriods();

            return redirect()->route('admin.students.index')->with('status', "{$existing->name} sudah memiliki akun mahasiswa dan kini ditambahkan ke periode ini. Data dan password akun tidak diubah.");
        }

        [$data, $grades] = $this->validated($request, $registration);
        $student = (new User($data + ['role' => 'mahasiswa']))->setCourseGrades($grades);
        $student->save();
        $student->joinPeriod(Period::workspace());

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
        // Status per periode; akun tidak dapat masuk bila nonaktif di semua periodenya.
        $active = ! $student->activeInPeriod();
        $student->joinPeriod(active: $active);
        $student->syncActiveFromPeriods();

        return back()->with('status', $active ? 'Mahasiswa diaktifkan di periode ini.' : 'Mahasiswa dinonaktifkan di periode ini.');
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

        $message = "Peminatan {$student->name} ".($student->specialization ? "diatur ke {$student->specialization}." : 'dikosongkan.');

        // Dari Detail tim (Registrasi Tim / Monitoring progres / Riwayat): kembali dan buka lagi detail timnya.
        return ($team ? TeamController::returnTo($team) : back())->with('status', $message);
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
        // Kelas dari aturan registrasi periode (kelas lama di luar daftar tetap boleh dipertahankan); opsional untuk admin.
        $classes = $options['classes']->push($student?->class_name)->filter()->unique()->all();
        $data = $request->validate([
            'nim' => ['required', 'string', 'max:20', Rule::unique('users', 'nim')->ignore($student?->id)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($student?->id)],
            'phone' => ['required', 'regex:/^\+?[0-9 \-]{8,20}$/'],
            'sks_passed' => ['required', 'integer', 'between:0,200'],
            'grades' => ['nullable', 'array'],
            'grades.*' => ['nullable', Rule::in($options['grades'])],
            'origin_region' => ['required', 'string', 'max:100'],
            'class_name' => ['nullable', 'string', 'max:30', ...($options['classes']->isEmpty() ? [] : [Rule::in($classes)])],
            'specialization' => ['nullable', 'string', Rule::in($specializations)],
            'password' => [$student ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
        ], ['phone.regex' => 'Nomor WhatsApp hanya berisi angka, spasi, atau tanda +.']);
        $data['email'] = Str::lower($data['email']);
        $data['specialization'] = ($data['specialization'] ?? null) ?: null;
        $data['class_name'] = ($data['class_name'] ?? null) ? Str::upper(trim($data['class_name'])) : null;
        $grades = $options['courses']->mapWithKeys(fn ($c) => [$c->code => $data['grades'][$c->id] ?? null])->filter()->all();
        unset($data['grades']);

        return [$data, $grades];
    }
}
