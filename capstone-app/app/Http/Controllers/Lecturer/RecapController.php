<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use App\Services\RecapExporter;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * F18 — Rekap Nilai Kelas (khusus dosen kelas): dosen kelas memilih kelas mahasiswa (semua kelas periode aktif dapat
 * dipilih), melihat rekapnya, lalu mengekspor nilai kelas itu dalam format template iGadis. Satu baris per mahasiswa
 * kelas tersebut (anggota lain tim yang berbeda kelas tidak ikut).
 */
class RecapController extends Controller
{
    public function index(Request $request, ScoreCalculator $calculator, RecapExporter $exporter): View
    {
        $this->authorizeClassLecturer($request);
        $classes = $this->classes();
        $class = $classes->contains($request->query('kelas')) ? $request->query('kelas') : null;
        // Semua kelas dikirim; pilihan kelas menyaring langsung di browser (data-filter-kelas), ?kelas hanya pilihan awal
        $results = $this->results(null, $calculator);

        return view('shared.recap', [
            'results' => $results,
            'columns' => collect($exporter->igadisColumns()),
            'exportRoute' => 'dosen.recap.export',
            'classes' => $classes,
            'class' => $class,
        ]);
    }

    public function export(Request $request, ScoreCalculator $calculator, RecapExporter $exporter): BinaryFileResponse|RedirectResponse
    {
        $this->authorizeClassLecturer($request);
        $class = $request->query('kelas');
        if (! $this->classes()->contains($class)) {
            return redirect()->route('dosen.recap.index')->withErrors(['export' => 'Pilih kelas yang akan diekspor.']);
        }
        ActivityLog::record('export_recap', null, ['mode' => 'igadis', 'kelas' => $class]);
        // Format template iGadis; nilai yang belum lengkap dikosongkan.
        $name = 'nilai-capstone-igadis-'.str($class)->slug().'-'.now()->format('Ymd-His').'.xlsx';

        return response()->download($exporter->toIgadis($this->results($class, $calculator)), $name)->deleteFileAfterSend();
    }

    private function authorizeClassLecturer(Request $request): void
    {
        abort_unless((bool) $request->user()->lecturerProfile?->is_class_lecturer, 403, 'Rekap nilai kelas hanya untuk dosen kelas.');
    }

    /** Kelas mahasiswa anggota tim diterima di periode aktif, urut nama kelas. */
    private function classes(): Collection
    {
        $period = Period::active();
        if (! $period) {
            return collect();
        }

        return User::query()->whereNotNull('class_name')
            ->whereHas('memberships', fn ($q) => $q->where('invite_status', 'diterima')
                ->whereHas('team', fn ($t) => $t->where('period_id', $period->id)->where('status', 'diterima')))
            ->distinct()->orderBy('class_name')->pluck('class_name')->values();
    }

    /**
     * Hasil per tim yang punya anggota di kelas ini; baris dibatasi ke mahasiswa kelas tersebut. Tanpa kelas: semua
     * mahasiswa yang punya kelas (tampilan Rekap Nilai Kelas, disaring per kelas di browser).
     */
    private function results(?string $class, ScoreCalculator $calculator): Collection
    {
        $period = Period::active();
        if (! $period) {
            return collect();
        }

        return Team::query()->where('period_id', $period->id)->where('status', 'diterima')
            ->whereHas('members', fn ($q) => $q->where('invite_status', 'diterima')
                ->whereHas('user', fn ($u) => $class === null ? $u->whereNotNull('class_name') : $u->where('class_name', $class)))
            ->orderBy('name')->get()
            ->map(function (Team $team) use ($calculator, $class) {
                $result = $calculator->forTeam($team);
                $result['rows'] = $result['rows']->filter(fn (array $row) => $class === null
                    ? $row['student']->class_name !== null
                    : $row['student']->class_name === $class)->values();

                return ['team' => $team] + $result;
            });
    }
}
