<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentComponent;
use App\Models\OralRubricLevel;
use App\Models\Period;
use App\Support\Sorting;
use App\Models\ReminderDispatch;
use App\Models\Team;
use App\Models\User;
use App\Services\Reminders\ReminderSender;
use App\Services\ReminderService;
use App\Services\ScoreCalculator;
use App\Services\TeamRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** F11 progres tim & dosen; F12 monitoring penilaian + reminder. */
class MonitoringController extends Controller
{
    /**
     * Monitoring progres tim (registrasi diterima): nama tim, topik final + asalnya (dosen/mandiri), dosen pembimbing &
     * penguji, log bimbingan, dan "Detail tim" (anggota, dokumen per milestone). Cari nama tim / nama anggota / judul
     * topik final; filter peminatan anggota tim.
     */
    public function progress(Request $request, TeamRegistration $registration, ScoreCalculator $calculator): View
    {
        $period = Period::workspace()?->load('milestones');
        $peminatan = (string) $request->query('peminatan');
        $term = '%'.$request->string('q').'%';
        $query = $period ? Team::query()->where('period_id', $period->id)->where('status', 'diterima')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $term)
                ->orWhereHas('members.user', fn ($u) => $u->where('name', 'like', $term))
                ->orWhereHas('finalTopic', fn ($t) => $t->where('title', 'like', $term))))
            ->when($peminatan !== '', fn ($q) => $q->whereHas('members', fn ($m) => $m->where('invite_status', 'diterima')
                ->whereHas('user', fn ($u) => $u->where('specialization', $peminatan))))
            ->with(['finalTopic', 'supervisor.lecturer.lecturerProfile', 'examiner.lecturer.lecturerProfile',
                'members' => fn ($m) => $m->where('invite_status', 'diterima')->orderByDesc('is_leader'), 'members.user'])
            ->withCount([
                'logbooks as logbook_total',
                'logbooks as logbook_approved' => fn ($q) => $q->where('status', 'disetujui'),
                'logbooks as logbook_pending' => fn ($q) => $q->where('status', 'menunggu'),
                'logbooks as logbook_revision' => fn ($q) => $q->where('status', 'revisi'),
            ])
            ->with(['documents' => fn ($q) => $q->where('state', 'aktif'), 'assessments.scores',
                'peerReviews:id,team_id,reviewer_id,reviewee_id,aspect,score']) : null;
        if ($query) {
            // Data tetap: bawaan kolom paling kiri (Nama tim) naik; kategori = status topik (dosen / mandiri / belum ada)
            Sorting::apply($query, $request, [
                'nama' => 'name',
                'terbaru' => 'created_at',
                'kategori' => fn ($q, $dir) => $q->orderBy(\App\Models\Topic::query()->select('source')->whereColumn('topics.id', 'teams.final_topic_id')->limit(1), $dir),
            ], 'nama');
        }
        $teams = $query?->paginate(25)->withQueryString();
        // Detail tim: hasil penilaian per bentuk penilaian & peer review (sama dengan detail tim dosen)
        $aspects = count(config('capstone.peer_aspects'));
        $teams?->getCollection()->each(function (Team $team) use ($calculator, $aspects) {
            $team->setAttribute('assessment_summary', $calculator->componentSummary($team));
            $expected = max(0, $team->members->count() - 1) * $aspects;
            $filled = $team->peerReviews->groupBy('reviewer_id')->filter(fn ($rows) => $expected > 0 && $rows->count() >= $expected)->keys();
            $team->setAttribute('peer_summary', ['done' => $filled->count(), 'total' => $team->members->count(), 'filled' => $filled]);
        });
        $options = $registration->options($period);

        return view('admin.monitoring.progress', $options + [
            'period' => $period,
            'teams' => $teams,
            'peminatan' => $peminatan,
            'openTeam' => $request->integer('tim'),
            'students' => $period ? TeamController::availableStudents($period) : collect(),
            'docMilestones' => $period?->milestones->where('kind', 'dokumen')->values() ?? collect(),
            'minLogbooks' => Period::minLogbooks($period),
        ]);
    }

    /**
     * Halaman penilaian satu bentuk penilaian satu tim untuk admin (tombol Detail di Hasil Penilaian, Monitoring progres):
     * tabel penilaian dosen yang sama, tetapi hanya untuk dilihat. Bentuk yang dinilai pembimbing & penguji punya pilihan
     * penilai (?penilai=pembimbing|penguji).
     */
    public function assessment(Request $request, Team $team, string $component, ScoreCalculator $calculator): View
    {
        abort_unless(array_key_exists($component, config('capstone.components')) && config("capstone.components.{$component}.role") !== 'mahasiswa', 404);
        $roles = AssessmentComponent::rolesOf($component);
        $role = in_array($request->query('penilai'), $roles, true) ? $request->query('penilai') : ($roles[0] ?? 'pembimbing');
        $assessments = Assessment::query()->with('scores', 'assessor')->where('team_id', $team->id)->where('component', $component)->get();
        // Satu penilai: penilaian mana pun untuk bentuk ini; dua penilai: menurut peran penilai
        $assessment = count($roles) <= 1
            ? $assessments->sortByDesc(fn ($a) => $a->status === 'final')->first()
            : $assessments->firstWhere('assessor_role', $role);
        $assigned = $team->lecturers()->where('role', $role)->with('lecturer')->first()?->lecturer;

        return view('dosen.assessments.form', [
            'team' => $team->load('members.user', 'finalTopic'),
            'componentKey' => $component,
            'label' => config("capstone.components.{$component}.label"),
            'role' => $role,
            'roles' => $roles,
            'assessorName' => $assessment?->assessor?->name ?? $assigned?->name,
            'indicators' => $calculator->indicatorsFor($component)->load('rubric'),
            'oralLevels' => OralRubricLevel::query()->orderBy('level')->get(),
            'assessment' => $assessment,
            'scores' => $assessment?->scores->keyBy('indicator_id') ?? collect(),
            'window' => null,
            'viaQr' => false,
            'calculator' => $calculator,
            'adminView' => true,
        ]);
    }

    public function assessments(Request $request, ReminderService $reminders): View
    {
        $period = Period::workspace();
        $rows = $period ? $reminders->incompleteAssessments($period) : collect();

        $byLecturer = $rows->groupBy(fn ($r) => $r['lecturer']->id)->map(function ($items) {
            $lecturer = $items->first()['lecturer'];
            $missing = $items->where('done', false);

            return [
                'lecturer' => $lecturer,
                'teams' => $items->pluck('team')->unique('id')->values(),
                // Kolom Tim Uji / Tim Bimbing
                'examTeams' => $items->where('role', 'penguji')->pluck('team')->unique('id')->sortBy('name')->values(),
                'superviseTeams' => $items->where('role', 'pembimbing')->pluck('team')->unique('id')->sortBy('name')->values(),
                // Kolom Komponen Penilaian: tiap bentuk penilaian yang menjadi tugas dosen ini → sudah dinilai / jumlah tim
                'components' => $items->groupBy('component')->map(fn ($rows) => ['done' => $rows->where('done', true)->count(), 'total' => $rows->count()]),
                'missing' => $missing->groupBy('component')->map->count(),
                'roles' => $items->pluck('role')->unique()->values(),
                // Modal Detail: satu baris per tim × peran, berisi status tiap bentuk penilaian
                'assignments' => $items->groupBy(fn ($r) => $r['team']->id.'-'.$r['role'])->map(fn ($rows) => [
                    'team' => $rows->first()['team'],
                    'role' => $rows->first()['role'],
                    'components' => $rows->map(fn ($r) => ['component' => $r['component'], 'done' => $r['done']])->values(),
                ])->sortBy(fn ($a) => [$a['role'] === 'penguji' ? 0 : 1, mb_strtolower($a['team']->name)])->values(),
                'complete' => $missing->isEmpty(),
            ];
        })
            // Peran & Status disaring langsung di browser (data-filter-*), jadi semua dosen dikirim
            ->sortBy(fn ($r) => [$r['complete'], $r['lecturer']->name])
            ->values();

        return view('admin.monitoring.assessments', [
            'period' => $period,
            'rows' => $byLecturer,
            'lastSent' => ReminderDispatch::query()->where('period_id', $period?->id)->where('trigger', 'manual')->selectRaw('lecturer_id, max(created_at) as at')->groupBy('lecturer_id')->pluck('at', 'lecturer_id'),
        ]);
    }

    public function remind(Request $request, ReminderService $reminders, ReminderSender $sender): RedirectResponse
    {
        $data = $request->validate([
            'lecturer_ids' => ['required', 'array', 'min:1'],
            'lecturer_ids.*' => [User::lecturerRule(active: false)],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,whatsapp'],
        ], [
            'lecturer_ids.required' => 'Pilih minimal satu dosen.',
            'channels.required' => 'Pilih minimal satu kanal pengiriman.',
        ]);

        $count = $reminders->queueManual(User::query()->whereIn('id', $data['lecturer_ids'])->get(), $data['channels'], $request->user());
        if (! $count) {
            return back()->with('status', 'Tidak ada komponen nilai yang belum diisi untuk dosen terpilih.');
        }

        // REMINDER_SENDER=app: langsung dikirim (WhatsApp lewat Fonnte, email lewat SMTP)
        if ($sender->enabled()) {
            $result = $sender->sendQueued();

            return back()->with('status', "{$result['sent']} pengingat terkirim".($result['failed'] ? ", {$result['failed']} gagal" : '').'. Rincian di halaman Reminder Otomatis.');
        }

        return back()->with('status', "{$count} pengingat masuk antrean pengiriman. Status kirim tercatat di halaman Reminder Otomatis.");
    }
}
