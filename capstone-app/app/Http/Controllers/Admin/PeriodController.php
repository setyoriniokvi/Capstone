<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\StudyProgram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** F2 — CRUD periode, timeline, tenggat, jendela penilaian, dan folder Drive prodi. */
class PeriodController extends Controller
{
    public function index(): View
    {
        return view('admin.periods.index', [
            'periods' => Period::query()->withCount('teams')->latest('starts_on')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.periods.form', ['period' => new Period(['semester' => 'ganjil']), 'prodis' => StudyProgram::query()->orderBy('code')->get()]);
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
            'period' => $period->load('milestones', 'assessmentWindows', 'driveFolders'),
            'prodis' => StudyProgram::query()->orderBy('code')->get(),
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
        $period->delete();

        return redirect()->route('admin.periods.index')->with('status', 'Periode dihapus.');
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
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'academic_year' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'in:ganjil,genap'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'drive_root_folder_id' => ['nullable', 'string', 'max:128'],
        ], [
            'academic_year.regex' => 'Format tahun akademik: 2026/2027.',
        ]);
    }

    /** Jendela penilaian per bentuk penilaian & folder Drive per prodi. */
    private function syncExtras(Period $period, Request $request): void
    {
        $data = $request->validate([
            'windows' => ['array'],
            'windows.*.opens_at' => ['nullable', 'date'],
            'windows.*.due_at' => ['nullable', 'date'],
            'folders' => ['array'],
            'folders.*' => ['nullable', 'string', 'max:128'],
        ]);

        foreach (array_keys(config('capstone.components')) as $component) {
            if ($component === 'peer_review') {
                continue;
            }
            $window = $data['windows'][$component] ?? [];
            if (! empty($window['opens_at']) && ! empty($window['due_at'])) {
                if (strtotime($window['due_at']) <= strtotime($window['opens_at'])) {
                    throw ValidationException::withMessages(["windows.{$component}.due_at" => 'Tenggat penilaian harus setelah waktu dibuka.']);
                }
                $period->assessmentWindows()->updateOrCreate(['component' => $component], $window);
            } else {
                $period->assessmentWindows()->where('component', $component)->delete();
            }
        }

        foreach ($data['folders'] ?? [] as $prodi => $folderId) {
            filled($folderId)
                ? $period->driveFolders()->updateOrCreate(['prodi_code' => $prodi], ['folder_id' => trim($folderId)])
                : $period->driveFolders()->where('prodi_code', $prodi)->delete();
        }
    }
}
