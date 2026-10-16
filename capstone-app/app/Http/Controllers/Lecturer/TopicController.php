<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Topic;
use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F42 — CRUD topik dosen; setiap perubahan isi menunggu approval admin (F7). */
class TopicController extends Controller
{
    public function index(Request $request): View
    {
        $period = Period::active();

        return view('dosen.topics.index', [
            'topics' => $period ? Topic::query()->where('period_id', $period->id)->where('lecturer_id', $request->user()->id)
                ->withCount(['applications as accepted_count' => fn ($q) => $q->where('status', 'diterima'), 'applications as pending_count' => fn ($q) => $q->where('status', 'menunggu')])
                ->with(['applications' => fn ($q) => $q->where('status', 'diterima')->with('team')])
                ->latest()->get() : collect(),
            'canPropose' => (bool) $request->user()->lecturerProfile?->can_propose_topic,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizePropose($request);

        return view('dosen.topics.form', ['topic' => new Topic(['quota' => 1])]);
    }

    public function store(Request $request, TopicService $topics): RedirectResponse
    {
        $this->authorizePropose($request);
        $period = Period::active() ?? abort(409, 'Belum ada periode aktif.');
        $topics->saveLecturerTopic(null, $this->validated($request), $request->user(), $period->id);

        return redirect()->route('dosen.topics.index')->with('status', 'Topik dikirim ke admin untuk ditinjau sebelum masuk bank topik.');
    }

    public function edit(Request $request, Topic $topic): View
    {
        $this->authorizeOwner($request, $topic);

        return view('dosen.topics.form', ['topic' => $topic]);
    }

    public function update(Request $request, Topic $topic, TopicService $topics): RedirectResponse
    {
        $this->authorizeOwner($request, $topic);
        $updated = $topics->saveLecturerTopic($topic, $this->validated($request), $request->user(), $topic->period_id);

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

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:50', 'max:5000'],
            'field' => ['required', 'string', 'max:120'],
            'quota' => ['required', 'integer', 'between:1,20'],
        ], ['description.min' => 'Deskripsi topik minimal 50 karakter.']);
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
