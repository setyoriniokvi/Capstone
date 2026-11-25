<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Validation\LecturerComponentInput;
use App\Http\Validation\MilestoneInput;
use App\Models\ActivityLog;
use App\Models\AssessmentComponent;
use App\Models\Period;
use App\Models\User;
use App\Services\PeriodSetup;
use App\Support\PeriodContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * F2 — Kelola Periode (admin fakultas): daftar, buat, ubah, aktif/nonaktif, hapus, dan buka Dashboard Periode.
 * Controller ini hanya memvalidasi isian; penyimpanan periode beserta pengaturannya ada di PeriodSetup.
 */
class PeriodController extends Controller
{
    /** Dashboard awal admin: daftar periode untuk dikelola atau dibuka sebagai Dashboard Periode. */
    public function index(): View
    {
        // Kelola Periode = dashboard awal di luar periode; membukanya berarti keluar dari Dashboard Periode.
        session()->forget(Period::WORKSPACE_KEY);
        $periods = Period::query()->latest('starts_on')->get();
        $ids = $periods->pluck('id');

        // Mahasiswa terdaftar = anggota tim (diterima) pada periode; dosen terdaftar = pembimbing/penguji atau dosen yang mengusulkan topik.
        $students = DB::table('team_members')->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->where('team_members.invite_status', 'diterima')->whereIn('teams.period_id', $ids)
            ->groupBy('teams.period_id')->selectRaw('teams.period_id, count(distinct team_members.user_id) as total')->pluck('total', 'period_id');
        $lecturers = DB::table('team_lecturers')->join('teams', 'teams.id', '=', 'team_lecturers.team_id')->whereIn('teams.period_id', $ids)
            ->select('teams.period_id', 'team_lecturers.lecturer_id')
            ->union(DB::table('topics')->where('source', 'dosen')->whereIn('period_id', $ids)->select('period_id', 'lecturer_id'))
            ->get()->groupBy('period_id')->map(fn ($rows) => $rows->pluck('lecturer_id')->unique()->count());

        return view('admin.periods.index', [
            'periods' => $periods,
            'studentCounts' => $students,
            'lecturerCounts' => $lecturers,
            'teamCounts' => DB::table('teams')->whereIn('period_id', $ids)->groupBy('period_id')->selectRaw('period_id, count(*) as total')->pluck('total', 'period_id'),
        ]);
    }

    public function enter(Request $request, Period $period): RedirectResponse
    {
        // Admin prodi hanya dapat membuka periode yang memakai akunnya.
        abort_unless($request->user()->canOpenPeriod($period), 403, 'Periode ini tidak dikelola akun Anda.');
        session([Period::WORKSPACE_KEY => $period->id]);
        PeriodContext::refresh();

        return redirect()->route('admin.dashboard')->with('status', "Dashboard periode {$period->name} dibuka.");
    }

    public function leave(): RedirectResponse
    {
        session()->forget(Period::WORKSPACE_KEY);

        return redirect()->route('admin.periods.index');
    }

    public function create(): View
    {
        $sources = Period::query()->latest('starts_on')->orderByDesc('id')->get();

        return view('admin.periods.form', [
            'period' => new Period(['semester' => 'ganjil']),
            'rule' => null,
            'sources' => $sources,
            'lecturerComponents' => collect(),
            // Jadwal penilaian periode baru: bentuk penilaian dosen milik tiap sumber data master ('' = bawaan)
            'componentSets' => collect(['' => null])->merge($sources->pluck('id', 'id'))
                ->map(fn ($id) => AssessmentComponent::inPeriod($id)->orderBy('sort_order')->orderBy('id')->get()
                    ->reject->isPeerReview()->values()),
        ]);
    }

    public function store(Request $request, PeriodSetup $setup): RedirectResponse
    {
        $source = $request->validate([
            'master_source' => ['nullable', 'integer', 'exists:periods,id'],
        ])['master_source'] ?? null;
        $admin = $this->validatedAdmin($request);
        $data = $this->validatedPeriod($request);
        $milestones = $this->draftMilestones($request);
        $components = $this->draftComponents($request);
        $rule = $this->validatedRule($request);

        $period = $setup->create(
            $data,
            $source ? (int) $source : null,
            $milestones,
            (array) $request->input('drive_folders', []),
            (array) $request->input('windows', []),
            $components,
            $rule,
            $admin,
        );
        ActivityLog::record('create_period', $period);

        return redirect()->route('admin.periods.edit', $period)->with('status', 'Periode dibuat.');
    }

    public function edit(Period $period): View
    {
        return view('admin.periods.form', [
            'period' => $period->load('milestones', 'assessmentWindows', 'admin'),
            'sharedPeriods' => $period->admin ? $period->admin->adminPeriods()->whereKeyNot($period->id)->get() : collect(),
            'rule' => $period->registrationRule()->with('courses', 'specializations')->first(),
            // Bentuk penilaian dosen periode ini (peer review diisi mahasiswa, tidak dijadwalkan di sini).
            'lecturerComponents' => AssessmentComponent::query()->orderBy('sort_order')->orderBy('id')->get()->reject->isPeerReview()->values(),
        ]);
    }

    public function update(Request $request, Period $period, PeriodSetup $setup): RedirectResponse
    {
        $admin = $this->validatedAdmin($request);
        $setup->update($period, $this->validatedPeriod($request), $this->validatedRule($request), (array) $request->input('drive_folders', []), $admin);

        return back()->with('status', 'Periode diperbarui.');
    }

    /**
     * Periode kosong cukup dikonfirmasi. Periode yang sudah punya tim harus dinonaktifkan dulu dan admin mengetik nama
     * periode persis (confirm_name).
     */
    public function destroy(Request $request, Period $period, PeriodSetup $setup): RedirectResponse
    {
        $hasData = $period->teams()->exists();
        if ($hasData && $period->is_active) {
            return back()->withErrors(['period' => "Nonaktifkan {$period->name} terlebih dahulu sebelum menghapusnya."]);
        }
        if ($hasData && trim((string) $request->input('confirm_name')) !== $period->name) {
            return back()->withErrors(['period' => "Nama periode tidak sesuai. Ketik \"{$period->name}\" untuk menghapus periode beserta datanya."]);
        }

        $name = $period->name;
        $wasOpen = session(Period::WORKSPACE_KEY) === $period->id;
        $teams = $setup->delete($period);
        if ($wasOpen) {
            session()->forget(Period::WORKSPACE_KEY);
        }
        ActivityLog::record('delete_period', null, ['period' => $name, 'teams' => $teams]);

        return redirect()->route('admin.periods.index')->with('status', "Periode {$name} dihapus.");
    }

    /** Periode tidak lagi dipakai mahasiswa & dosen (periode aktif lain tetap berjalan). */
    public function deactivate(Period $period): RedirectResponse
    {
        $period->update(['is_active' => false]);

        return back()->with('status', "{$period->name} dinonaktifkan.");
    }

    public function activate(Period $period): RedirectResponse
    {
        // Beberapa periode boleh aktif bersamaan (tiap prodi berjalan paralel); periode lain tidak ikut dinonaktifkan.
        $period->update(['is_active' => true]);

        return back()->with('status', "{$period->name} sekarang aktif.");
    }

    private function validatedPeriod(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'academic_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'in:ganjil,genap'],
            'prodi_name' => ['required', 'string', 'max:150'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'min_approved_logbooks' => ['required', 'integer', 'between:0,50'],
        ], [
            'academic_year.regex' => 'Format tahun akademik: 2026/2027.',
            'prodi_name.required' => 'Isi nama prodi.',
            'min_approved_logbooks.required' => 'Isi minimal log bimbingan (0 bila tanpa syarat).',
        ]);
    }

    /** F5 — isian aturan registrasi tim (rule[...]); teks per baris diurai PeriodSetup. */
    private function validatedRule(Request $request): array
    {
        $rule = $request->validate([
            'rule.min_sks' => ['required', 'integer', 'between:0,160'],
            'rule.min_members' => ['required', 'integer', 'between:1,10'],
            'rule.max_members' => ['required', 'integer', 'gte:rule.min_members', 'between:1,10'],
            'rule.courses' => ['nullable', 'string', 'max:5000'],
            'rule.specializations' => ['nullable', 'string', 'max:5000'],
            'rule.classes' => ['nullable', 'string', 'max:5000'],
        ], [
            'rule.max_members.gte' => 'Maksimal anggota tidak boleh kurang dari minimal anggota.',
        ])['rule'];

        return $rule + [
            'require_distinct_region' => $request->boolean('rule.require_distinct_region'),
            'require_distinct_specialization' => $request->boolean('rule.require_distinct_specialization'),
        ];
    }

    /**
     * Kartu "Akun admin prodi": email + password akun yang langsung masuk ke Dashboard Periode ini. Email yang sama
     * di beberapa periode = satu akun (password berlaku untuk semuanya); akun tersebut dapat berganti periode.
     *
     * @return array{email: ?string, password: ?string}
     */
    private function validatedAdmin(Request $request): array
    {
        $data = $request->validate([
            'admin_email' => ['nullable', 'email', 'max:150'],
            'admin_password' => ['nullable', 'string', 'min:8', 'max:100'],
        ], [
            'admin_email.email' => 'Format email admin prodi tidak valid.',
            'admin_password.min' => 'Password admin prodi minimal 8 karakter.',
        ]);
        $email = filled($data['admin_email'] ?? null) ? Str::lower(trim($data['admin_email'])) : null;
        $password = filled($data['admin_password'] ?? null) ? $data['admin_password'] : null;

        if ($email) {
            $owner = User::query()->where('email', $email)->first();
            if ($owner && ! $owner->isProdiAdmin()) {
                throw ValidationException::withMessages(['admin_email' => "Email ini sudah dipakai akun {$owner->roleLabel()}. Gunakan email lain untuk admin prodi."]);
            }
            if (! $owner && ! $password) {
                throw ValidationException::withMessages(['admin_password' => 'Isi password untuk akun admin prodi baru.']);
            }
        } elseif ($password) {
            throw ValidationException::withMessages(['admin_email' => 'Isi email akun admin prodi.']);
        }

        return ['email' => $email, 'password' => $password];
    }

    /**
     * Milestone yang disusun di halaman Periode baru (JSON draft_milestones), divalidasi seperti form milestone.
     *
     * @return array<string, array<string, mixed>> kunci draf (d1, d2, …) => isian milestone
     */
    private function draftMilestones(Request $request): array
    {
        $items = json_decode((string) $request->input('draft_milestones', '[]'), true);
        if (! is_array($items)) {
            throw ValidationException::withMessages(['draft_milestones' => 'Data milestone tidak terbaca. Muat ulang halaman lalu susun kembali milestone.']);
        }
        $clean = [];
        foreach (array_slice($items, 0, 100) as $index => $item) {
            $item = is_array($item) ? $item : [];
            $name = trim((string) ($item['name'] ?? '')) ?: 'ke-'.($index + 1);
            try {
                $data = MilestoneInput::validate($item);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['draft_milestones' => "Milestone {$name}: ".collect($e->errors())->flatten()->first()]);
            }
            $key = preg_match('/^d\d{1,4}$/', (string) ($item['key'] ?? '')) ? $item['key'] : 'd'.($index + 1);
            $clean[$key] = $data;
        }

        return $clean;
    }

    /**
     * Bentuk penilaian dosen baru dari halaman Periode baru (JSON draft_components). Nama unik dicek PeriodSetup
     * terhadap data master yang disalin.
     *
     * @return list<array{label: string, roles: list<string>, document_types: list<string>, opens_at: ?string, due_at: ?string}>
     */
    private function draftComponents(Request $request): array
    {
        $items = json_decode((string) $request->input('draft_components', '[]'), true);
        if (! is_array($items)) {
            return [];
        }
        $clean = [];
        foreach (array_slice($items, 0, 30) as $item) {
            $item = is_array($item) ? $item : [];
            try {
                $clean[] = LecturerComponentInput::validate($item, uniqueLabel: false);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['draft_components' => 'Bentuk penilaian '.($item['label'] ?? '').': '.collect($e->errors())->flatten()->first()]);
            }
        }

        return $clean;
    }
}
