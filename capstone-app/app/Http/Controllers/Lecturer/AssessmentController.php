<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Assessment;
use App\Models\AssessmentWindow;
use App\Models\OralRubricLevel;
use App\Models\Period;
use App\Models\Team;
use App\Services\ReminderService;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** F47 / F58 / F59 / F60 — penilaian berbasis rubrik oleh pembimbing & penguji. */
class AssessmentController extends Controller
{
    public function index(Request $request, ReminderService $reminders): View
    {
        $period = Period::active();
        $rows = $period ? $reminders->incompleteAssessments($period, $request->user()->id) : collect();
        $drafts = Assessment::query()->where('assessor_id', $request->user()->id)->where('status', 'draft')->get()
            ->mapWithKeys(fn ($a) => ["{$a->team_id}:{$a->component}" => true]);

        $category = $request->input('kategori');
        $rows = $rows->when($category === 'bimbingan', fn ($c) => $c->where('role', 'pembimbing'))
            ->when($category === 'uji', fn ($c) => $c->where('role', 'penguji'))
            ->when($request->filled('component'), fn ($c) => $c->where('component', $request->input('component')));

        return view('dosen.assessments.index', [
            'rows' => $rows->sortBy(fn ($r) => [$r['done'], $r['team']->code])->values(),
            'drafts' => $drafts,
            'windows' => $period ? $period->assessmentWindows()->get()->keyBy('component') : collect(),
            'category' => $category,
        ]);
    }

    public function edit(Request $request, Team $team, string $component, ScoreCalculator $calculator): View
    {
        $role = $this->authorizeComponent($request, $team, $component);
        $assessment = Assessment::query()->with('scores')->where('team_id', $team->id)->where('component', $component)->where('assessor_id', $request->user()->id)->first();

        return view('dosen.assessments.form', [
            'team' => $team->load('members.user', 'finalTopic'),
            'componentKey' => $component,
            'label' => config("capstone.components.{$component}.label"),
            'role' => $role,
            'indicators' => $calculator->indicatorsFor($component)->load('rubric'),
            'oralLevels' => OralRubricLevel::query()->orderBy('level')->get(),
            'assessment' => $assessment,
            'scores' => $assessment?->scores->keyBy('indicator_id') ?? collect(),
            'window' => AssessmentWindow::query()->where('period_id', $team->period_id)->where('component', $component)->first(),
            'viaQr' => $request->input('via') === 'qr',
            'calculator' => $calculator,
            'scoreLevels' => config('capstone.score_levels'),
        ]);
    }

    public function update(Request $request, Team $team, string $component, ScoreCalculator $calculator): RedirectResponse
    {
        $role = $this->authorizeComponent($request, $team, $component);
        $indicators = $calculator->indicatorsFor($component);
        $final = $request->input('action') === 'final';

        $rules = ['feedback' => [$final ? 'required' : 'nullable', 'string', 'max:5000']];
        foreach ($indicators as $indicator) {
            $rules["scores.{$indicator->id}.score"] = [$final ? 'required' : 'nullable', 'integer', 'between:0,5'];
            if ($indicator->usesOralFactor()) {
                $rules["scores.{$indicator->id}.oral_level"] = [$final ? 'required' : 'nullable', 'integer', 'exists:oral_rubric_levels,level'];
            }
        }
        $data = $request->validate($rules, [
            'scores.*.score.required' => 'Semua indikator wajib diberi skor sebelum dikirim final.',
            'scores.*.oral_level.required' => 'Pilih level pemahaman lisan untuk setiap indikator lisan.',
            'feedback.required' => 'Tuliskan feedback untuk tim sebelum mengirim nilai final.',
        ]);

        $existing = Assessment::query()->where('team_id', $team->id)->where('component', $component)->where('assessor_id', $request->user()->id)->first();
        if ($existing?->status === 'final' && ! $final) {
            throw ValidationException::withMessages(['scores' => 'Nilai sudah final. Simpan sebagai final untuk memperbarui.']);
        }

        $factors = OralRubricLevel::query()->pluck('factor', 'level');

        DB::transaction(function () use ($team, $component, $role, $request, $data, $indicators, $final, $factors) {
            $assessment = Assessment::query()->updateOrCreate(
                ['team_id' => $team->id, 'component' => $component, 'assessor_id' => $request->user()->id],
                [
                    'assessor_role' => $role,
                    'status' => $final ? 'final' : 'draft',
                    'feedback' => $data['feedback'] ?? null,
                    'channel' => $request->input('via') === 'qr' ? 'qr' : 'web',
                    'submitted_at' => $final ? now() : null,
                ]
            );

            foreach ($indicators as $indicator) {
                $input = $data['scores'][$indicator->id] ?? [];
                if (! isset($input['score']) || $input['score'] === null) {
                    $assessment->scores()->where('indicator_id', $indicator->id)->delete();

                    continue;
                }
                $oral = $indicator->usesOralFactor() ? ($input['oral_level'] ?? null) : null;
                $assessment->scores()->updateOrCreate(['indicator_id' => $indicator->id], [
                    'score' => (int) $input['score'],
                    'oral_level' => $oral,
                    'factor' => $oral ? (float) $factors[$oral] : 1,
                ]);
            }
        });

        ActivityLog::record($final ? 'submit_assessment' : 'draft_assessment', $team, ['component' => $component]);

        return $final
            ? redirect()->route('dosen.assessments.index')->with('status', "Nilai {$team->name} · ".config("capstone.components.{$component}.label").' tersimpan final.')
            : back()->with('status', 'Draf penilaian disimpan.');
    }

    /** F59 — dosen hanya menilai tim & tahap yang menjadi kewenangannya. */
    private function authorizeComponent(Request $request, Team $team, string $component): string
    {
        abort_unless(array_key_exists($component, config('capstone.components')) && $component !== 'peer_review', 404);
        $role = $team->lecturerRole($request->user());
        $required = config("capstone.components.{$component}.role");
        abort_unless($role && $role === $required, 403, 'Komponen ini bukan kewenangan Anda untuk tim tersebut.');

        $window = AssessmentWindow::query()->where('period_id', $team->period_id)->where('component', $component)->first();
        if ($window && ! $window->isOpen() && $request->isMethod('put')) {
            throw ValidationException::withMessages(['window' => 'Jendela penilaian '.$window->label().' '.(now()->lt($window->opens_at) ? 'belum dibuka.' : 'sudah ditutup.')]);
        }

        // PRD 8.2 — sidang (presentasi akhir) terbuka setelah minimal 8 log disetujui.
        if (str_starts_with($component, 'sidang') && $request->isMethod('put') && $team->approvedLogbookCount() < config('capstone.min_approved_logbooks')) {
            throw ValidationException::withMessages(['gate' => 'Minimal '.config('capstone.min_approved_logbooks').' logbook harus disetujui sebelum presentasi akhir.']);
        }

        return $role;
    }
}
