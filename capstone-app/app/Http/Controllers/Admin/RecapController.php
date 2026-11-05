<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\Team;
use App\Services\RecapExporter;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** F18 — rekap nilai akhir otomatis dan ekspor per mahasiswa (format template iGadis, atau rekap penuh bila semua tim lengkap). */
class RecapController extends Controller
{
    public function index(Request $request, ScoreCalculator $calculator): View
    {
        $results = $this->results($request, $calculator);

        return view('shared.recap', [
            'results' => $results,
            'clos' => $calculator->clos(),
            'exportRoute' => 'admin.recap.export',
            'complete' => $results->where('complete', true)->count(),
        ]);
    }

    public function export(Request $request, ScoreCalculator $calculator, RecapExporter $exporter): BinaryFileResponse|RedirectResponse
    {
        $igadis = $request->input('mode') === 'igadis';
        $results = $this->results($request, $calculator);

        // Ekspor iGadis dapat kapan saja (nilai yang belum lengkap dikosongkan); ekspor penuh hanya bila semua tim lengkap (Alur §3.9).
        if (! $igadis && $results->contains('complete', false)) {
            return back()->withErrors(['export' => 'Masih ada tim yang komponen nilainya belum lengkap. Gunakan ekspor iGadis atau lengkapi penilaian.']);
        }

        ActivityLog::record('export_recap', null, ['mode' => $igadis ? 'igadis' : 'penuh']);
        if ($igadis) {
            return response()->download($exporter->toIgadis($results), 'nilai-capstone-igadis-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
        }

        return response()->download($exporter->toXlsx($results, false), 'rekap-nilai-capstone-'.now()->format('Ymd-His').'.xlsx')->deleteFileAfterSend();
    }

    private function results(Request $request, ScoreCalculator $calculator): Collection
    {
        $period = Period::workspace();
        if (! $period) {
            return collect();
        }

        return Team::query()->where('period_id', $period->id)->where('status', 'diterima')
            ->orderBy('name')->get()
            ->map(fn (Team $team) => ['team' => $team] + $calculator->forTeam($team));
    }
}
