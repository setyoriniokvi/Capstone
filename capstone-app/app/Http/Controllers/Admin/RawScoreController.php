<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Indicator;
use App\Models\PeerReview;
use App\Models\Period;
use App\Models\Team;
use App\Services\ScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Raw Data Penilaian (sidebar admin → Pemantauan; langkah pertama sebelum Rekap Penilaian). Satu baris per mahasiswa tim
 * yang diterima — NIM | Nama | Kelas | Nama Tim, lalu dua tampilan (tab), keduanya memuat seluruh assessment sekaligus:
 *   ?tampilan=clo (bawaan, "Per Assessment/CLO"): judul assessment → CLO → "CLOx | Lisan | Total CLOx", lalu Total
 *                 Assessment (Lisan hanya bila CLO itu dinilai lisan; peer review: Teman 1 | Teman 2 | … | Total CLOx);
 *   ?tampilan=indikator: semua indikator dikelompokkan per CLO, digabung dari seluruh assessment yang menilainya
 *                        (Dokumen & Lisan = rata-rata, Nilai = jumlah); kolom mengikuti skema indikator.
 * Peer review menampilkan nilai dari tiap teman satu tim (rata-rata aspek per penilai), bukan kolom Lisan.
 * Hanya penilaian final yang dihitung (sama dengan Rekap Penilaian).
 */
class RawScoreController extends Controller
{
    public const VIEWS = ['clo' => 'Per Assessment/CLO', 'indikator' => 'Per Indikator'];

    public function index(Request $request, ScoreCalculator $calculator): View
    {
        $period = Period::workspace();
        $teams = $period
            ? Team::query()->where('period_id', $period->id)->where('status', 'diterima')->with('activeUsers')->orderBy('name')->get()
            : collect();
        $columns = $calculator->rawColumns();
        // Tautan lama (?tampilan=assessment / ?bentuk=) diarahkan ke Per Assessment/CLO
        $view = $request->query('tampilan') === 'indikator' ? 'indikator' : 'clo';

        // Peer review: nilai dari tiap teman (rata-rata aspek per penilai); banyak kolom = teman terbanyak dalam satu tim
        $peers = $this->peerScores($teams);
        $data = ['view' => $view, 'columns' => $columns,
            'peerSlots' => max(1, (int) $teams->max(fn (Team $t) => $t->activeUsers->count() - 1))];

        if ($view === 'clo') {
            $data['rows'] = $teams->flatMap(fn (Team $team) => $calculator->rawForTeam($team)
                ->map(fn (array $row) => $row + ['team' => $team, 'peers' => $peers[$row['student']->id] ?? []]));

            return view('admin.raw-scores', $data);
        }

        // Per indikator: indikator yang berbobot di salah satu assessment, gabungan seluruh assessment
        $indicators = $calculator->indicators()->filter(fn (Indicator $i) => $columns->keys()->contains(fn ($c) => $i->weightFor($c) > 0))->values();
        $data['weights'] = $indicators->mapWithKeys(fn (Indicator $i) => [$i->id => $columns->keys()->sum(fn ($c) => $i->weightFor($c))]);
        $data['rows'] = $teams->flatMap(fn (Team $team) => $this->combined($team, $columns->keys(), $calculator)
            ->map(fn (array $row) => $row + ['peers' => $peers[$row['student']->id] ?? []]));
        // Kelompok CLO (judul) → indikator, urut kode CLO lalu kode indikator
        $data['groups'] = $indicators->sortBy([fn ($a, $b) => strnatcmp((string) $a->clo?->code, (string) $b->clo?->code), fn ($a, $b) => strnatcmp($a->code, $b->code)])
            ->groupBy(fn (Indicator $i) => $i->clo?->code ?? '—');

        return view('admin.raw-scores', $data);
    }

    /**
     * Nilai peer review per mahasiswa dari tiap teman: [reviewee_id => list<{name, score}>] urut nama penilai;
     * score = rata-rata nilai aspek yang diberikan penilai itu.
     *
     * @return array<int, list<array{name: string, score: float}>>
     */
    private function peerScores(Collection $teams): array
    {
        $names = $teams->flatMap(fn (Team $t) => $t->activeUsers)->pluck('name', 'id');

        return PeerReview::query()->whereIn('team_id', $teams->pluck('id'))->get()
            ->groupBy('reviewee_id')
            ->map(fn (Collection $received) => $received->groupBy('reviewer_id')
                ->map(fn (Collection $rows, $reviewer) => ['name' => (string) ($names[$reviewer] ?? 'Teman'), 'score' => (float) $rows->avg('score')])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all())
            ->all();
    }

    /**
     * Gabungan seluruh assessment untuk tampilan per indikator: per mahasiswa, tiap indikator diambil dari assessment
     * yang sudah final (peer review: lengkap). Dokumen / rata-rata peer & Lisan = rata-rata, Nilai = jumlah.
     */
    private function combined(Team $team, Collection $components, ScoreCalculator $calculator): Collection
    {
        $raws = $components->mapWithKeys(fn ($c) => [$c => $calculator->rawIndicatorsForTeam($team, $c)->keyBy(fn (array $r) => $r['student']->id)]);
        $students = $raws->first()?->pluck('student') ?? collect();

        return $students->map(function ($student) use ($raws, $team) {
            $parts = [];
            foreach ($raws as $rows) {
                $row = $rows->get($student->id);
                if (! $row || ! $row['ready']) {
                    continue;
                }
                foreach ($row['cells'] as $indicatorId => $cell) {
                    $parts[$indicatorId][] = $cell;
                }
            }
            $cells = collect($parts)->map(function (array $list) {
                $orals = array_filter(array_column($list, 'oral'), fn ($v) => $v !== null);
                $levels = array_filter(array_column($list, 'level'), fn ($v) => $v !== null);

                return [
                    'doc' => array_sum(array_column($list, 'doc')) / count($list),
                    'level' => count($list) === 1 && count($levels) === 1 ? reset($levels) : null,
                    'oral' => $orals ? array_sum($orals) / count($orals) : null,
                    'value' => array_sum(array_column($list, 'value')),
                ];
            })->all();

            return ['student' => $student, 'team' => $team, 'ready' => $cells !== [], 'cells' => $cells, 'total' => array_sum(array_column($cells, 'value'))];
        });
    }
}
