<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Milestone;
use App\Models\Period;
use App\Models\RegistrationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** F2 — CRUD periode, timeline, deadline, jendela penilaian, dan folder Drive periode. */
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
        ]);
    }

    public function enter(Period $period): RedirectResponse
    {
        session([Period::WORKSPACE_KEY => $period->id]);

        return redirect()->route('admin.dashboard')->with('status', "Dashboard periode {$period->name} dibuka.");
    }

    public function leave(): RedirectResponse
    {
        session()->forget(Period::WORKSPACE_KEY);

        return redirect()->route('admin.periods.index');
    }

    public function create(): View
    {
        return view('admin.periods.form', ['period' => new Period(['semester' => 'ganjil']), 'rule' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $period = DB::transaction(function () use ($request) {
            $period = Period::create($this->validated($request));
            $this->syncExtras($period, $request);

            return $period;
        });
        ActivityLog::record('create_period', $period);

        return redirect()->route('admin.periods.edit', $period)->with('status', 'Periode dibuat. Lanjutkan dengan menyusun milestone.');
    }

    public function edit(Period $period): View
    {
        return view('admin.periods.form', [
            'period' => $period->load('milestones', 'assessmentWindows'),
            'rule' => $period->registrationRule()->with('courses', 'specializations')->first(),
        ]);
    }

    public function update(Request $request, Period $period): RedirectResponse
    {
        DB::transaction(function () use ($request, $period) {
            $period->update($this->validated($request));
            $this->syncExtras($period, $request);
        });

        return back()->with('status', 'Periode diperbarui.');
    }

    public function destroy(Period $period): RedirectResponse
    {
        if ($period->teams()->exists()) {
            return back()->withErrors(['period' => 'Periode yang sudah memiliki tim tidak dapat dihapus.']);
        }
        if (session(Period::WORKSPACE_KEY) === $period->id) {
            session()->forget(Period::WORKSPACE_KEY);
        }
        $period->delete();

        return redirect()->route('admin.periods.index')->with('status', 'Periode dihapus.');
    }

    /** Periode tidak lagi dipakai mahasiswa & dosen (tidak ada periode aktif sampai periode lain diaktifkan). */
    public function deactivate(Period $period): RedirectResponse
    {
        $period->update(['is_active' => false]);

        return back()->with('status', "{$period->name} dinonaktifkan.");
    }

    public function activate(Period $period): RedirectResponse
    {
        DB::transaction(function () use ($period) {
            Period::query()->update(['is_active' => false]);
            $period->update(['is_active' => true]);
        });

        return back()->with('status', "{$period->name} sekarang menjadi periode aktif.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
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
        return $data;
    }

    /** Aturan registrasi tim dan folder Drive per milestone dokumen. Jadwal pengisian nilai dikelola terpisah (AssessmentComponentController). */
    private function syncExtras(Period $period, Request $request): void
    {
        $this->syncRule($period, $request);
        $this->syncDriveFolders($period, $request);
    }

    /**
     * F35 — satu folder Google Drive Database per milestone dokumen (isian mengikuti milestone dokumen yang ada).
     * Boleh diisi tautan folder atau ID-nya; kosong = dokumen milestone tersebut belum disinkronkan ke Drive.
     */
    private function syncDriveFolders(Period $period, Request $request): void
    {
        $input = (array) $request->input('drive_folders', []);
        $milestones = $period->milestones()->where('kind', 'dokumen')->get();
        $errors = [];
        $folders = [];

        foreach ($milestones as $milestone) {
            $value = trim((string) ($input[$milestone->id] ?? ''));
            $folders[$milestone->id] = $value === '' ? null : Milestone::parseDriveFolder($value);
            if ($value !== '' && $folders[$milestone->id] === null) {
                $errors["drive_folders.{$milestone->id}"] = 'Tautan folder Google Drive tidak dikenali. Salin tautan folder dari address bar Google Drive.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($milestones as $milestone) {
            if (array_key_exists($milestone->id, $input)) {
                $milestone->update(['drive_folder_id' => $folders[$milestone->id]]);
            }
        }
    }

    /**
     * F5 — satu aturan registrasi per periode: minimal SKS, jumlah anggota, syarat multikultural/multidisiplin,
     * mata kuliah prasyarat (satu per baris "KODE - Nama") dan peminatan (satu per baris).
     */
    private function syncRule(Period $period, Request $request): void
    {
        $data = $request->validate([
            'rule.min_sks' => ['required', 'integer', 'between:0,160'],
            'rule.min_members' => ['required', 'integer', 'between:1,10'],
            'rule.max_members' => ['required', 'integer', 'gte:rule.min_members', 'between:1,10'],
            'rule.courses' => ['nullable', 'string', 'max:5000'],
            'rule.specializations' => ['nullable', 'string', 'max:5000'],
        ], [
            'rule.max_members.gte' => 'Maksimal anggota tidak boleh kurang dari minimal anggota.',
        ])['rule'];

        $rule = RegistrationRule::query()->where('period_id', $period->id)->orderBy('id')->first() ?? new RegistrationRule;
        $rule->fill([
            'min_sks' => $data['min_sks'],
            'min_members' => $data['min_members'],
            'max_members' => $data['max_members'],
            'require_distinct_region' => $request->boolean('rule.require_distinct_region'),
            'require_distinct_specialization' => $request->boolean('rule.require_distinct_specialization'),
        ]);
        $period->registrationRules()->save($rule);

        $lines = fn (?string $text) => collect(preg_split('/\r\n|\r|\n/', (string) $text))->map(fn ($l) => trim($l))->filter()->values();

        $courses = $lines($data['courses'] ?? null)->map(function (string $line) {
            [$code, $name] = array_pad(preg_split('/\s+-\s+|\s*\|\s*/', $line, 2), 2, null);

            return ['code' => Str::upper(trim($code)), 'name' => trim($name ?? $code)];
        })->unique('code');
        $rule->courses()->whereNotIn('code', $courses->pluck('code'))->delete();
        foreach ($courses as $course) {
            $rule->courses()->updateOrCreate(['code' => $course['code']], ['name' => $course['name']]);
        }

        $specializations = $lines($data['specializations'] ?? null)->unique();
        $rule->specializations()->whereNotIn('name', $specializations)->delete();
        foreach ($specializations as $name) {
            $rule->specializations()->firstOrCreate(['name' => $name]);
        }
    }
}
