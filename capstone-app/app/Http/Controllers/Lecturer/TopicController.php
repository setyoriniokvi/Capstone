<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\RegistrationRule;
use App\Models\StudyProgram;
use App\Models\Topic;
use App\Models\TopicInstruction;
use App\Models\User;
use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F42 — topik dosen. Daftar berisi topik dosen lain yang sudah terbit di bank topik dan seluruh topik yang diusulkan
 * dosen sendiri (sebagai pengusul utama atau pengusul bersama, apa pun statusnya). Seluruh dosen pengusul dapat
 * mengubah/menghapus topik. Perubahan isi menunggu approval admin (F7).
 */
class TopicController extends Controller
{
    public function index(Request $request): View
    {
        $period = Period::active();
        $userId = $request->user()->id;

        $all = $period ? Topic::query()->where('period_id', $period->id)->where('source', 'dosen')
            ->where(fn ($q) => $q->where('status', 'diterima')->orWhere(fn ($q) => $q->proposedBy($userId)))
            ->with('lecturer', 'proposers')
            ->withCount([
                'applications as accepted_count' => fn ($q) => $q->where('status', 'diterima'),
                'applications as pending_count' => fn ($q) => $q->where('status', 'menunggu'),
            ])
            ->get() : collect();

        // Formulir tambah/edit di atas halaman dibuka di browser tanpa memuat ulang (Alpine "topicForm").
        // ?tambah=1 / ?edit={id} hanya untuk membuka formulir dari tautan lama.
        $formTopic = new Topic(['quota' => 1]);
        if ($request->filled('edit')) {
            $formTopic = Topic::query()->findOrFail($request->integer('edit'));
            $this->authorizeOwner($request, $formTopic);
        }

        $mine = $all->filter(fn (Topic $t) => $t->isProposer($request->user()));

        return view('dosen.topics.index', $this->formData($request, $period?->id) + [
            'formTopic' => $formTopic,
            'instruction' => TopicInstruction::for('dosen'),
            'formOpen' => $request->filled('edit') || $request->boolean('tambah'),
            'topics' => Topic::filterBank($all, (string) $request->query('q'), (string) $request->query('peminatan')),
            'specializations' => $all->pluck('specializations')->flatten()->filter()->unique()->sort()->values(),
            // Ringkasan topik yang diusulkan dosen: jumlah topik, yang menunggu approval admin, dan tim pelamar yang menunggu keputusan.
            'summary' => [
                'topics' => $mine->count(),
                'awaiting' => $mine->where('status', 'menunggu')->count(),
                'applicants' => $mine->sum('pending_count'),
            ],
        ]);
    }

    /** Formulir kini berada di atas halaman Topik Dosen; tautan lama diarahkan ke sana. */
    public function create(): RedirectResponse
    {
        return redirect()->route('dosen.topics.index', ['tambah' => 1]);
    }

    public function store(Request $request, TopicService $topics): RedirectResponse
    {
        $period = Period::active() ?? abort(409, 'Belum ada periode aktif.');
        $topics->saveLecturerTopic(null, $this->validated($request, $period->id, $topics), $request->user(), $period->id);

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
        $updated = $topics->saveLecturerTopic($topic, $this->validated($request, $topic->period_id, $topics), $request->user(), $topic->period_id);

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

    /**
     * Pilihan formulir: prodi, peminatan periode (diatur admin di Aturan Registrasi), dan dosen lain yang dapat
     * diundang sebagai pengusul (dicari menurut nama / NIP / peminatan).
     */
    private function formData(Request $request, ?int $periodId): array
    {
        $specializations = $periodId ? RegistrationRule::query()->where('period_id', $periodId)->with('specializations')->get()
            ->flatMap(fn (RegistrationRule $rule) => $rule->specializations->pluck('name'))->unique()->sort()->values() : collect();

        return [
            'programs' => StudyProgram::query()->orderBy('name')->get(),
            'prodiSpecializations' => $specializations,
            'lecturers' => User::query()->where('role', 'dosen')->where('is_active', true)->whereKeyNot($request->user()->id)
                ->with('lecturerProfile')->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, int $periodId, TopicService $topics): array
    {
        [$rules, $messages] = $topics->lecturerTopicRules($periodId);

        return $request->validate($rules, $messages);
    }

    /** Seluruh dosen pengusul (utama maupun bersama) boleh mengubah / menghapus topik. */
    private function authorizeOwner(Request $request, Topic $topic): void
    {
        abort_unless($topic->source === 'dosen' && $topic->isProposer($request->user()), 403);
    }
}
