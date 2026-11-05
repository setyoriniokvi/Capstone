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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * F47 / F58 / F59 / F60 — penilaian berbasis rubrik oleh pembimbing & penguji. Formulir berupa tabel rubrik (master data
 * penilaian admin, seperti Rubrik Dokumen): dosen mencentang indikator — satu per satu, beberapa, atau satu CLO sekaligus —
 * lalu memilih skor (dan pemahaman lisan) dan "Simpan penilaian" (scores), atau "Hapus pilihan" (destroyScores) untuk
 * mengosongkan skornya. Skor langsung tersimpan (real time) dan status ikut dihitung ulang: lengkap ("final") bila
 * semua indikator sudah dinilai — masuk rekap & terlihat mahasiswa — selain itu sebagian ("draft"). Feedback lewat update().
 */
class AssessmentController extends Controller
{
    public function index(Request $request, ReminderService $reminders): View
    {
        $period = Period::active();
        $rows = $period ? $reminders->incompleteAssessments($period, $request->user()->id) : collect();
        // Info tim di tiap baris: anggota & jenis topik final
        EloquentCollection::make($rows->pluck('team')->unique('id')->values())->load(['members.user', 'finalTopic']);
        // "Sebagian dinilai" hanya bila sudah ada skor tersimpan; draf tanpa skor (mis. hanya feedback) = belum dinilai.
        $drafts = Assessment::query()->where('assessor_id', $request->user()->id)->where('status', 'draft')->has('scores')->get()
            ->mapWithKeys(fn ($a) => ["{$a->team_id}:{$a->component}" => true]);

        // Tab = bentuk penilaian yang menjadi wewenang dosen (urutan bentuk penilaian); ?komponen= memilih tab, bawaan tab pertama.
        $components = collect(array_keys(config('capstone.components')))->intersect($rows->pluck('component'))->values();
        $active = $components->contains($request->query('komponen')) ? $request->query('komponen') : $components->first();

        return view('dosen.assessments.index', [
            'components' => $components,
            'active' => $active,
            // Sisa tim yang belum lengkap dinilai per bentuk penilaian (angka di tab)
            'pendingCounts' => $rows->where('done', false)->countBy('component'),
            // Tim pada tab aktif: belum lengkap dulu, lalu urut nama
            'rows' => $rows->where('component', $active)->sortBy(fn ($r) => [$r['done'], mb_strtolower($r['team']->name)])->values(),
            'drafts' => $drafts,
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
            'scoreLevels' => config('capstone.score_levels'),
        ]);
    }

    /** Simpan penilaian: satu skor (dan level pemahaman lisan) untuk semua indikator yang dicentang. */
    public function scores(Request $request, Team $team, string $component, ScoreCalculator $calculator): RedirectResponse
    {
        $role = $this->authorizeComponent($request, $team, $component);
        $indicators = $calculator->indicatorsFor($component);
        $data = $request->validate([
            'indicator_ids' => ['required', 'array', 'min:1'],
            'indicator_ids.*' => ['integer', 'distinct', Rule::in($indicators->pluck('id')->all())],
            'score' => ['required', 'integer', 'between:0,5'],
            'oral_level' => ['nullable', 'integer', 'exists:oral_rubric_levels,level'],
        ], [
            'indicator_ids.required' => 'Centang indikator yang akan dinilai.',
            'score.required' => 'Pilih skor.',
        ]);

        $selected = $indicators->whereIn('id', $data['indicator_ids']);
        if (empty($data['oral_level']) && $selected->contains(fn ($i) => $i->usesOralFactor())) {
            throw ValidationException::withMessages(['oral_level' => 'Pilih pemahaman lisan — ada indikator berskema lisan yang dicentang.']);
        }
        $factor = empty($data['oral_level']) ? 1.0 : (float) OralRubricLevel::query()->where('level', $data['oral_level'])->value('factor');

        DB::transaction(function () use ($team, $component, $role, $request, $selected, $data, $factor, $indicators) {
            $assessment = Assessment::query()->firstOrCreate(
                ['team_id' => $team->id, 'component' => $component, 'assessor_id' => $request->user()->id],
                ['assessor_role' => $role, 'status' => 'draft', 'channel' => $request->input('via') === 'qr' ? 'qr' : 'web']
            );
            foreach ($selected as $indicator) {
                $oral = $indicator->usesOralFactor() ? (int) $data['oral_level'] : null;
                $assessment->scores()->updateOrCreate(['indicator_id' => $indicator->id], [
                    'score' => (int) $data['score'],
                    'oral_level' => $oral,
                    'factor' => $oral ? $factor : 1,
                ]);
            }
            $this->syncStatus($assessment, $indicators);
        });

        ActivityLog::record('score_indicators', $team, ['component' => $component, 'indicators' => $selected->pluck('code')->all(), 'score' => (int) $data['score']]);

        return back()->with('status', $selected->count().' indikator dinilai skor '.$data['score'].'.');
    }

    /** Hapus pilihan: kosongkan skor indikator yang dicentang; status dihitung ulang (tidak lengkap lagi → sebagian). */
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
            DB::transaction(function () use ($assessment, $data, $indicators, &$deleted) {
                $deleted = $assessment->scores()->whereIn('indicator_id', $data['indicator_ids'])->delete();
                $this->syncStatus($assessment, $indicators);
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

    /**
     * Status mengikuti skor secara real time: lengkap (status "final", masuk rekap & terlihat mahasiswa) bila semua
     * indikator bentuk penilaian ini sudah dinilai, selain itu "draft" (sebagian dinilai).
     */
    private function syncStatus(Assessment $assessment, Collection $indicators): void
    {
        $scored = $assessment->scores()->pluck('indicator_id');
        $complete = $indicators->every(fn ($i) => $scored->contains($i->id));
        $assessment->update([
            'status' => $complete ? 'final' : 'draft',
            'submitted_at' => $complete ? ($assessment->submitted_at ?? now()) : null,
        ]);
    }

    /** F59 — dosen hanya menilai tim & tahap yang menjadi kewenangannya. */
    private function authorizeComponent(Request $request, Team $team, string $component): string
    {
        abort_unless(array_key_exists($component, config('capstone.components')) && $component !== 'peer_review', 404);
        $role = $team->lecturerRole($request->user());
        abort_unless($role && in_array($role, AssessmentComponent::rolesOf($component), true), 403, 'Komponen ini bukan kewenangan Anda untuk tim tersebut.');

        $window = AssessmentWindow::query()->where('period_id', $team->period_id)->where('component', $component)->first();
        if ($window && ! $window->isOpen() && ! $request->isMethod('get')) {
            throw ValidationException::withMessages(['window' => 'Jendela penilaian '.$window->label().' '.(now()->lt($window->opens_at) ? 'belum dibuka.' : 'sudah ditutup.')]);
        }

        // PRD 8.2 — sidang (presentasi akhir) terbuka setelah minimal log disetujui (aturan tambahan periode).
        $minLogbooks = Period::minLogbooks($team->period);
        if (str_starts_with($component, 'sidang') && ! $request->isMethod('get') && $team->approvedLogbookCount() < $minLogbooks) {
            throw ValidationException::withMessages(['gate' => 'Minimal '.$minLogbooks.' logbook harus disetujui sebelum presentasi akhir.']);
        }

        return $role;
    }
}
