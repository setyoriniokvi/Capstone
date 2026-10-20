<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\StudyProgram;
use App\Models\Team;
use App\Services\RecapExporter;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** F18 — rekap nilai akhir otomatis dan ekspor per mahasiswa. */
class RecapController extends Controller
{
    public function index(Request $request, ScoreCalculator $calculator): View
    {
        $results = $this->results($request, $calculator);

        return view('shared.recap', [
            'results' => $results,
            'clos' => $calculator->clos(),
            'prodis' => StudyProgram::query()->orderBy('code')->get(),
            'exportRoute' => 'admin.recap.export',
            'complete' => $results->where('complete', true)->count(),
            'showProdiFilter' => true,
        ]);
    }

    public function export(Request $request, ScoreCalculator $calculator, RecapExporter $exporter): BinaryFileResponse|RedirectResponse
    {
        $partial = $request->input('mode') === 'parsial';
        $results = $this->results($request, $calculator);

        // Ekspor penuh hanya aktif bila semua tim lengkap (Alur §3.9).
        if (! $partial && $results->contains('complete', false)) {
            return back()->withErrors(['export' => 'Masih ada tim yang komponen nilainya belum lengkap. Gunakan ekspor parsial atau lengkapi penilaian.']);
        }

        ActivityLog::record('export_recap', null, ['mode' => $partial ? 'parsial' : 'penuh', 'prodi' => $request->input('prodi')]);
        $name = 'rekap-nilai-capstone'.($request->filled('prodi') ? '-'.$request->input('prodi') : '').'-'.now()->format('Ymd-His').'.xlsx';

        return response()->download($exporter->toXlsx($results, $partial), $name)->deleteFileAfterSend();
    }

    private function results(Request $request, ScoreCalculator $calculator): Collection
    {
        $period = Period::workspace();
        if (! $period) {
            return collect();
        }

        return Team::query()->where('period_id', $period->id)->where('status', 'diterima')
            ->when($request->filled('prodi'), fn ($q) => $q->where('prodi_code', $request->string('prodi')))
            ->orderBy('code')->get()
            ->map(fn (Team $team) => ['team' => $team] + $calculator->forTeam($team));
    }
}
