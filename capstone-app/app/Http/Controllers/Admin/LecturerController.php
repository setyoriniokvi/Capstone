<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LecturerProfile;
use App\Support\PeriodContext;
use App\Support\Sorting;
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
        // Daftar dosen periode yang sedang dibuka (akun dosen dapat terdaftar di beberapa periode).
        $query = User::query()->lecturers()->inPeriod()->withPeriodStatus()->with('lecturerProfile')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)
                    ->orWhereHas('lecturerProfile', fn ($q) => $q->where('lecturer_code', 'like', $term)));
            })
            ->when(array_key_exists($filter, LecturerProfile::ACCESS), fn ($q) => $q->whereHas('lecturerProfile', fn ($p) => $p->where($filter, true)));
        $profile = fn (string $column) => fn ($q, $dir) => $q->orderBy(LecturerProfile::query()->select($column)->whereColumn('lecturer_profiles.user_id', 'users.id')->limit(1), $dir);
        // Data tetap: bawaan kolom paling kiri (Kode) naik
        Sorting::apply($query, $request, ['kode' => $profile('lecturer_code'), 'nama' => 'name', 'terbaru' => 'created_at', 'kategori' => $profile('specialization')], 'kode');
        $lecturers = $query->paginate(25)->withQueryString();

        return view('admin.lecturers.index', ['lecturers' => $lecturers]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Dosen yang sudah punya akun (terdaftar di periode lain) cukup ditambahkan ke periode ini: email, nama, password
        // tetap; profil periode ini (kode, NIP, peminatan, WhatsApp) disalin dari periode terakhirnya lalu dapat diubah.
        $existing = User::query()->lecturers()->where('email', Str::lower(trim((string) $request->input('email'))))->first();
        if ($existing) {
            if ($existing->periods()->whereKey(PeriodContext::id())->exists()) {
                return back()->withErrors(['email' => 'Dosen dengan email ini sudah terdaftar di periode ini.'])->withInput();
            }
            $existing->joinPeriod(active: true);
            $existing->syncActiveFromPeriods();

            return redirect()->route('admin.lecturers.index')->with('status', "{$existing->name} sudah memiliki akun dosen dan kini ditambahkan ke periode ini. Profilnya disalin dari periode sebelumnya; ubah bila berbeda untuk periode ini.");
        }

        $data = $this->validated($request);
        DB::transaction(function () use ($data) {
            $user = User::create($data['user'] + ['role' => 'dosen', 'password' => $data['password'], 'phone' => $data['profile']['phone']]);
            $user->lecturerProfile()->create($data['profile']);
            $user->joinPeriod();
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
        // Status per periode: menonaktifkan di periode ini tidak memengaruhi periode lain. Akun tidak dapat masuk
        // bila nonaktif di semua periodenya.
        $active = ! $lecturer->activeInPeriod();
        $lecturer->joinPeriod(active: $active);
        $lecturer->syncActiveFromPeriods();

        return back()->with('status', $active ? 'Dosen diaktifkan di periode ini.' : 'Dosen dinonaktifkan di periode ini.');
    }

    /** Input manual: semua isian wajib (impor Excel/CSV boleh mengosongkan kolom yang belum ada); password wajib saat tambah. */
    private function validated(Request $request, ?User $lecturer = null): array
    {
        $profileId = $lecturer?->lecturerProfile?->id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($lecturer?->id)],
            'phone' => ['required', 'regex:/^\+?[0-9 \-]{8,20}$/'],
            // Kode dosen & NIP unik di dalam satu periode (profil dosen per periode).
            'lecturer_code' => ['required', 'string', 'max:10', Rule::unique('lecturer_profiles', 'lecturer_code')->where('period_id', PeriodContext::id())->ignore($profileId)],
            'nip' => ['required', 'string', 'max:30', Rule::unique('lecturer_profiles', 'nip')->where('period_id', PeriodContext::id())->ignore($profileId)],
            'specialization' => ['required', 'string', 'max:150'],
            'password' => [$lecturer ? 'nullable' : 'required', 'string', 'min:6', 'max:100'],
        ], ['phone.regex' => 'Nomor WhatsApp hanya berisi angka, spasi, atau tanda +.']);

        return [
            'password' => $data['password'] ?? null,
            'user' => [
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
            ],
            // Nomor WhatsApp per periode (dipakai reminder periode ini)
            'profile' => [
                'phone' => $data['phone'],
                'lecturer_code' => Str::upper($data['lecturer_code']),
                'nip' => $data['nip'],
                'specialization' => $data['specialization'],
                'is_class_lecturer' => $request->boolean('is_class_lecturer'),
                'can_supervise' => $request->boolean('can_supervise'),
                'can_examine' => $request->boolean('can_examine'),
            ],
        ];
    }
}
