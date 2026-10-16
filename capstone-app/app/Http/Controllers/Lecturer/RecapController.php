<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Team;
use App\Services\RecapExporter;
use App\Services\ScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** F18 — rekap nilai tim yang dibimbing / diuji dosen. */
class RecapController extends Controller
{
    public function index(Request $request, ScoreCalculator $calculator): View
    {
        $results = $this->results($request, $calculator);

        return view('shared.recap', [
            'results' => $results,
            'clos' => $calculator->clos(),
            'prodis' => collect(),
            'exportRoute' => 'dosen.recap.export',
            'complete' => $results->where('complete', true)->count(),
            'showProdiFilter' => false,
        ]);
    }

    public function export(Request $request, ScoreCalculator $calculator, RecapExporter $exporter): BinaryFileResponse
    {
        $name = 'rekap-nilai-'.($request->user()->lecturerProfile?->lecturer_code ?? 'dosen').'-'.now()->format('Ymd-His').'.xlsx';

        return response()->download($exporter->toXlsx($this->results($request, $calculator), partial: true), $name)->deleteFileAfterSend();
    }

    private function results(Request $request, ScoreCalculator $calculator): Collection
    {
        $period = Period::active();
        if (! $period) {
            return collect();
        }

        return Team::query()->where('period_id', $period->id)->where('status', 'diterima')
            ->whereHas('lecturers', fn ($q) => $q->where('lecturer_id', $request->user()->id))
            ->orderBy('code')->get()
            ->map(fn (Team $team) => ['team' => $team] + $calculator->forTeam($team));
    }
}
