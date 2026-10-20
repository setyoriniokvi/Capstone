<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\RegistrationRule;
use App\Models\RuleSpecialization;
use App\Models\StudyProgram;
use App\Models\Topic;
use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * F42 — topik dosen. Daftar berisi topik dosen lain yang sudah terbit di bank topik dan seluruh topik milik dosen
 * sendiri (apa pun statusnya); hanya topik sendiri yang dapat diubah/dihapus. Perubahan isi menunggu approval admin (F7).
 */
class TopicController extends Controller
{
    public function index(Request $request): View
    {
        $period = Period::active();
        $userId = $request->user()->id;

        $all = $period ? Topic::query()->where('period_id', $period->id)->where('source', 'dosen')
            ->where(fn ($q) => $q->where('lecturer_id', $userId)->orWhere('status', 'diterima'))
            ->with('lecturer', 'studyProgram')
            ->withCount([
                'applications as accepted_count' => fn ($q) => $q->where('status', 'diterima'),
                'applications as pending_count' => fn ($q) => $q->where('status', 'menunggu'),
            ])
            ->get() : collect();

        // Formulir tambah/edit di atas halaman dibuka di browser tanpa memuat ulang (Alpine "topicForm").
        // ?tambah=1 / ?edit={id} hanya untuk membuka formulir dari tautan lama.
        $formTopic = new Topic(['quota' => 1, 'prodi_code' => $request->user()->prodi_code]);
        if ($request->filled('edit')) {
            $formTopic = Topic::query()->findOrFail($request->integer('edit'));
            $this->authorizeOwner($request, $formTopic);
        }

        return view('dosen.topics.index', ($period ? $this->formData($formTopic, $period->id) : ['programs' => collect(), 'prodiSpecializations' => collect()]) + [
            'formTopic' => $formTopic,
            'formOpen' => $request->filled('edit') || $request->boolean('tambah'),
            'topics' => Topic::filterBank($all, (string) $request->query('q'), (string) $request->query('peminatan')),
            'specializations' => $all->pluck('competencies')->filter()->unique()->sort()->values(),
            // Ringkasan topik milik dosen: jumlah topik, yang menunggu approval admin, dan tim pelamar yang menunggu keputusan.
            'summary' => [
                'topics' => $all->where('lecturer_id', $userId)->count(),
                'awaiting' => $all->where('lecturer_id', $userId)->where('status', 'menunggu')->count(),
                'applicants' => $all->where('lecturer_id', $userId)->sum('pending_count'),
            ],
            'canPropose' => (bool) $request->user()->lecturerProfile?->can_propose_topic,
        ]);
    }

    /** Formulir kini berada di atas halaman Topik Dosen; tautan lama diarahkan ke sana. */
    public function create(): RedirectResponse
    {
        return redirect()->route('dosen.topics.index', ['tambah' => 1]);
    }

    public function store(Request $request, TopicService $topics): RedirectResponse
    {
        $this->authorizePropose($request);
        $period = Period::active() ?? abort(409, 'Belum ada periode aktif.');
        $topics->saveLecturerTopic(null, $this->validated($request, $period->id), $request->user(), $period->id);

        return redirect()->route('dosen.topics.index')->with('status', 'Topik dikirim ke admin untuk ditinjau sebelum masuk bank topik.');
    }

    public function edit(Request $request, Topic $topic): RedirectResponse
    {
        $this->authorizeOwner($request, $topic);

        return redirect()->route('dosen.topics.index', ['edit' => $topic->id]);
    }

    public function update(Request $request, Topic $topic, TopicService $topics): RedirectResponse
    {
        $this->authorizeOwner($request, $topic);
        $updated = $topics->saveLecturerTopic($topic, $this->validated($request, $topic->period_id), $request->user(), $topic->period_id);

        return redirect()->route('dosen.topics.index')->with('status', $updated->status === 'menunggu'
            ? 'Perubahan topik dikirim ulang ke admin untuk ditinjau.'
            : 'Kuota topik diperbarui.');
    }

    public function destroy(Request $request, Topic $topic): RedirectResponse
    {
        $this->authorizeOwner($request, $topic);
        if ($topic->applications()->whereIn('status', ['menunggu', 'diterima'])->exists()) {
            return back()->withErrors(['topic' => 'Topik yang sudah memiliki pelamar tidak dapat dihapus.']);
        }
        $topic->delete();

        return back()->with('status', 'Topik dihapus.');
    }

    /** Prodi beserta peminatannya pada periode topik (diatur admin di Aturan Registrasi). */
    private function formData(Topic $topic, int $periodId): array
    {
        $specializations = RegistrationRule::query()->where('period_id', $periodId)->with('specializations')->get()
            ->flatMap(fn (RegistrationRule $rule) => $rule->specializations->pluck('name'))->unique()->sort()->values();

        return [
            'programs' => StudyProgram::query()->orderBy('name')->get(),
            'prodiSpecializations' => $specializations,
        ];
    }

    private function validated(Request $request, int $periodId): array
    {
        // Kompetensi yang diharapkan = salah satu peminatan pada aturan registrasi periode ini.
        $allowed = RuleSpecialization::query()->whereHas('rule', fn ($q) => $q->where('period_id', $periodId))->pluck('name')->all();

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'prodi_code' => ['required', 'string', 'exists:study_programs,code'],
            'competencies' => ['required', 'string', Rule::in($allowed)],
            'object' => ['required', 'string', 'max:255'],
            'quota' => ['required', 'integer', 'between:1,20'],
        ], [
            'competencies.required' => 'Pilih peminatan yang diharapkan.',
            'competencies.in' => 'Pilih peminatan yang tersedia pada periode ini.',
            'object.required' => 'Isi instansi, organisasi, atau perusahaan objek.',
        ]);
    }

    private function authorizePropose(Request $request): void
    {
        abort_unless($request->user()->lecturerProfile?->can_propose_topic, 403, 'Akun Anda belum memiliki hak akses sebagai pengusul topik.');
    }

    private function authorizeOwner(Request $request, Topic $topic): void
    {
        abort_unless($topic->source === 'dosen' && $topic->lecturer_id === $request->user()->id, 403);
    }
}
