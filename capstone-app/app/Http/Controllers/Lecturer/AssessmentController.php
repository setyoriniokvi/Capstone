<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\AssessmentComponent;
use App\Models\AssessmentWindow;
use App\Models\OralRubricLevel;
use App\Models\Period;
use App\Models\Team;
use App\Services\ReminderService;
use App\Services\ScoreCalculator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\PeriodContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * F47 / F58 / F59 / F60 — penilaian berbasis rubrik oleh pembimbing & penguji. Formulir berupa tabel rubrik (master data
 * penilaian admin, seperti Rubrik Dokumen): dosen mencentang indikator — satu per satu, beberapa, atau satu CLO sekaligus —
 * lalu memilih skor (dan pemahaman lisan) dan "Simpan Draft" atau "Simpan Final" (scores), atau "Hapus pilihan"
 * (destroyScores) untuk mengosongkan skornya. Draft = belum dinilai (belum masuk rekap, tidak terlihat mahasiswa, tetap
 * kena reminder). Simpan Final hanya bila semua indikator sudah dinilai → status "final": sudah dinilai, masuk rekap &
 * terlihat mahasiswa. Mengubah / menghapus skor lewat Simpan Draft atau Hapus pilihan mengembalikannya ke draft.
 * Feedback lewat update().
 */
class AssessmentController extends Controller
{
    public function index(Request $request, ReminderService $reminders, ScoreCalculator $calculator): View
    {
        $period = Period::active();
        $rows = $period ? $reminders->incompleteAssessments($period, $request->user()->id) : collect();
        // Info tim di tiap baris: anggota & jenis topik final
        EloquentCollection::make($rows->pluck('team')->unique('id')->values())->load(['members.user', 'finalTopic']);

        // Tab = bentuk penilaian yang menjadi wewenang dosen (urutan bentuk penilaian); ?komponen= memilih tab, bawaan tab pertama.
        // Hanya bentuk penilaian yang dinilai peran dosen ini (pembimbing saja → tanpa penilaian penguji, dan sebaliknya).
        $roles = $request->user()->lecturerRoles();
        $components = collect(array_keys(config('capstone.components')))->intersect($rows->pluck('component'))
            ->filter(fn ($c) => array_intersect(AssessmentComponent::rolesOf($c), $roles) !== [])->values();
        $active = $components->contains($request->query('komponen')) ? $request->query('komponen') : $components->first();

        // Kolom Nilai: total nilai penilaian final dosen ini untuk tim pada tab aktif (poin dari bobot maksimal bentuk penilaian)
        $activeRows = $rows->where('component', $active);
        $indicators = $active ? $calculator->indicatorsFor($active) : collect();
        $scores = $active ? Assessment::query()->with('scores')->where('component', $active)->where('assessor_id', $request->user()->id)
            ->where('status', 'final')->whereIn('team_id', $activeRows->pluck('team.id'))->get()
            ->mapWithKeys(fn (Assessment $a) => [$a->team_id => round($a->scores->sum(function ($score) use ($indicators, $calculator, $active) {
                $indicator = $indicators->firstWhere('id', $score->indicator_id);

                return $indicator ? $calculator->indicatorValue($indicator, $active, $score->score, (float) $score->factor) : 0;
            }), 2)]) : collect();

        // Nilai tim pada skala 0–100 (Σ nilai indikator ÷ total bobot bentuk penilaian × 100), sama dengan tampilan
        // mahasiswa dan kolom Rekap Penilaian / iGadis.
        $maxPoints = round($indicators->sum(fn ($i) => $i->weightFor($active)), 2);
        $scores = $scores->map(fn ($points) => $maxPoints > 0 ? round($points / $maxPoints * 100, 2) : 0.0);

        return view('dosen.assessments.index', [
            'scores' => $scores,
            'components' => $components,
            'active' => $active,
            // Sisa tim yang belum lengkap dinilai per bentuk penilaian (angka di tab)
            'pendingCounts' => $rows->where('done', false)->countBy('component'),
            // Tim pada tab aktif: belum lengkap dulu, lalu urut nama
            'rows' => $rows->where('component', $active)->sortBy(fn ($r) => [$r['done'], mb_strtolower($r['team']->name)])->values(),
            'window' => $period && $active ? $period->assessmentWindows()->where('component', $active)->first() : null,
        ]);
    }

    public function edit(Request $request, Team $team, string $component, ScoreCalculator $calculator): View
    {
        $role = $this->authorizeComponent($request, $team, $component);
        $assessment = Assessment::query()->with('scores')->where('team_id', $team->id)->where('component', $component)->where('assessor_id', $request->user()->id)->first();
        $indicators = $calculator->indicatorsFor($component)->load('rubric');

        return view('dosen.assessments.form', [
            'team' => $team->load('members.user', 'finalTopic'),
            'componentKey' => $component,
            'label' => config("capstone.components.{$component}.label"),
            'role' => $role,
            'indicators' => $indicators,
            'oralLevels' => OralRubricLevel::query()->orderBy('level')->get(),
            'assessment' => $assessment,
            'scores' => $assessment?->scores->keyBy('indicator_id') ?? collect(),
            'window' => AssessmentWindow::query()->where('period_id', $team->period_id)->where('component', $component)->first(),
            'viaQr' => $request->input('via') === 'qr',
            'calculator' => $calculator,
        ]);
    }

    /**
     * Simpan Draft / Simpan Final dari tabel penilaian: interval (0 … interval maksimum indikator = banyaknya poin
     * rubrik) dan, untuk indikator berskema lisan, level pemahaman lisan per baris — scores[indicator_id][score|oral_level].
     * Interval kosong = skor indikator itu dikosongkan. Simpan Final ditolak bila masih ada indikator yang belum dinilai.
     */
    public function scores(Request $request, Team $team, string $component, ScoreCalculator $calculator): RedirectResponse|JsonResponse
    {
        $role = $this->authorizeComponent($request, $team, $component);
        $indicators = $calculator->indicatorsFor($component)->keyBy('id');
        // Simpan otomatis (dari browser setiap kali interval / lisan diubah): selalu draft, baris yang belum lengkap
        // (mis. interval sudah dipilih tetapi pemahaman lisan belum) dilewati tanpa galat, jawaban berupa JSON.
        $autosave = $request->boolean('autosave');
        $final = ! $autosave && $request->input('mode') === 'final';
        $input = (array) $request->input('scores', []);
        $factors = OralRubricLevel::query()->pluck('factor', 'level');

        $rows = [];
        $errors = [];
        foreach ($input as $id => $row) {
            $indicator = $indicators->get((int) $id);
            if (! $indicator || ! is_array($row)) {
                continue;
            }
            $score = $row['score'] ?? null;
            if ($score === null || $score === '') {
                $rows[$indicator->id] = null; // dikosongkan

                continue;
            }
            $max = $indicator->maxInterval();
            if (! ctype_digit((string) $score) || (int) $score > $max) {
                if (! $autosave) {
                    $errors["scores.{$indicator->id}.score"] = "Interval {$indicator->code} harus 0–{$max}.";
                }

                continue;
            }
            $oral = $row['oral_level'] ?? null;
            // Pemahaman lisan wajib untuk indikator lisan yang dinilai (interval 0 = tidak mengumpulkan, tanpa lisan).
            if ($indicator->usesOralFactor($component) && (int) $score > 0 && ! $factors->has((int) $oral)) {
                if (! $autosave) {
                    $errors["scores.{$indicator->id}.oral_level"] = "Pilih pemahaman lisan untuk {$indicator->code}.";
                }

                continue;
            }
            $useOral = $indicator->usesOralFactor($component) && (int) $score > 0;
            $rows[$indicator->id] = [
                'score' => (int) $score,
                'oral_level' => $useOral ? (int) $oral : null,
                'factor' => $useOral ? (float) $factors[(int) $oral] : 1,
            ];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        if (! $final && ! $autosave && $rows === []) {
            throw ValidationException::withMessages(['scores' => 'Isi interval minimal satu indikator.']);
        }
        $existing = Assessment::query()->where('team_id', $team->id)->where('component', $component)->where('assessor_id', $request->user()->id)->first();
        if ($autosave && ! $existing && array_filter($rows) === []) {
            // Belum ada penilaian dan belum ada interval terisi: tidak perlu membuat draft kosong
            return response()->json(['status' => 'none', 'scored' => [], 'total' => $indicators->count(), 'saved_at' => null]);
        }

        DB::transaction(function () use ($team, $component, $role, $request, $rows, $indicators, $final) {
            $assessment = Assessment::query()->firstOrCreate(
                ['team_id' => $team->id, 'component' => $component, 'assessor_id' => $request->user()->id],
                ['assessor_role' => $role, 'status' => 'draft', 'channel' => $request->input('via') === 'qr' ? 'qr' : 'web']
            );
            foreach ($rows as $indicatorId => $values) {
                $values === null
                    ? $assessment->scores()->where('indicator_id', $indicatorId)->delete()
                    : $assessment->scores()->updateOrCreate(['indicator_id' => $indicatorId], $values);
            }

            // Simpan Final hanya bila semua indikator sudah dinilai (dibatalkan seluruhnya bila belum)
            $scored = $assessment->scores()->pluck('indicator_id');
            $missing = $indicators->reject(fn ($i) => $scored->contains($i->id));
            if ($final && $missing->isNotEmpty()) {
                throw ValidationException::withMessages(['scores' => 'Submit Final hanya bisa dilakukan setelah semua indikator dinilai. Masih ada '.$missing->count().' indikator yang belum dinilai.']);
            }
            $assessment->update($final
                ? ['status' => 'final', 'submitted_at' => now()]
                : ['status' => 'draft', 'submitted_at' => null]);
        });

        if ($autosave) {
            $assessment = Assessment::query()->with('scores')->where('team_id', $team->id)->where('component', $component)->where('assessor_id', $request->user()->id)->first();

            return response()->json([
                'status' => $assessment?->status ?? 'none',
                'was_final' => $existing?->status === 'final',
                'scored' => $assessment?->scores->pluck('indicator_id')->values() ?? [],
                'total' => $indicators->count(),
                'saved_at' => now()->format('H:i'),
            ]);
        }

        $filled = array_filter($rows);
        ActivityLog::record($final ? 'finalize_assessment' : 'score_indicators', $team, ['component' => $component,
            'scores' => collect($filled)->mapWithKeys(fn ($v, $id) => [$indicators[$id]->code => $v['score']])->all()]);

        return back()->with('status', ($filled ? count($filled).' indikator tersimpan. ' : '').($final
            ? 'Penilaian disimpan final: sudah dinilai dan terlihat oleh mahasiswa.'
            : 'Disimpan sebagai draft: belum dinilai sampai Submit Final.'));
    }

    /** Hapus pilihan: kosongkan skor indikator yang dicentang; penilaian kembali menjadi draft (belum dinilai). */
    public function destroyScores(Request $request, Team $team, string $component, ScoreCalculator $calculator): RedirectResponse
    {
        $this->authorizeComponent($request, $team, $component);
        $indicators = $calculator->indicatorsFor($component);
        $data = $request->validate([
            'indicator_ids' => ['required', 'array', 'min:1'],
            'indicator_ids.*' => ['integer', 'distinct', Rule::in($indicators->pluck('id')->all())],
        ], ['indicator_ids.required' => 'Centang indikator yang skornya akan dihapus.']);

        $assessment = Assessment::query()->where('team_id', $team->id)->where('component', $component)->where('assessor_id', $request->user()->id)->first();
        $deleted = 0;
        if ($assessment) {
            DB::transaction(function () use ($assessment, $data, &$deleted) {
                $deleted = $assessment->scores()->whereIn('indicator_id', $data['indicator_ids'])->delete();
                if ($deleted) {
                    $assessment->update(['status' => 'draft', 'submitted_at' => null]);
                }
            });
        }
        ActivityLog::record('unscore_indicators', $team, ['component' => $component, 'indicators' => $indicators->whereIn('id', $data['indicator_ids'])->pluck('code')->all()]);

        return back()->with('status', $deleted ? "Skor {$deleted} indikator dihapus." : 'Indikator yang dipilih belum memiliki skor.');
    }

    /** Simpan feedback untuk tim (opsional); status penilaian tidak berubah. */
    public function update(Request $request, Team $team, string $component, ScoreCalculator $calculator): RedirectResponse
    {
        $role = $this->authorizeComponent($request, $team, $component);
        $data = $request->validate(['feedback' => ['nullable', 'string', 'max:5000']]);

        $assessment = Assessment::query()->firstOrCreate(
            ['team_id' => $team->id, 'component' => $component, 'assessor_id' => $request->user()->id],
            ['assessor_role' => $role, 'status' => 'draft', 'channel' => $request->input('via') === 'qr' ? 'qr' : 'web']
        );
        $assessment->update(['feedback' => $data['feedback'] ?? null]);
        ActivityLog::record('feedback_assessment', $team, ['component' => $component]);

        return back()->with('status', 'Feedback disimpan.');
    }

    /** F59 — dosen hanya menilai tim & tahap yang menjadi kewenangannya. */
    private function authorizeComponent(Request $request, Team $team, string $component): string
    {
        abort_unless(array_key_exists($component, config('capstone.components')) && $component !== 'peer_review', 404);
        $role = $team->lecturerRole($request->user());
        abort_unless($role && in_array($role, AssessmentComponent::rolesOf($component), true), 403, 'Komponen ini bukan kewenangan Anda untuk tim tersebut.');

        $window = AssessmentWindow::query()->where('period_id', $team->period_id)->where('component', $component)->first();
        // Jadwal penilaian hanya pengingat: setelah tenggat tetap dapat dinilai; sebelum dibuka belum dapat diisi.
        if ($window && ! $window->isOpen() && ! $request->isMethod('get')) {
            throw ValidationException::withMessages(['window' => 'Jendela penilaian '.$window->label().' belum dibuka.']);
        }

        // Minimal log bimbingan tidak mengunci penilaian sidang; syarat itu hanya untuk pengumpulan Laporan Akhir &
        // Materi Presentasi (Milestone::LOGBOOK_GATED_TYPES).

        return $role;
    }
}
