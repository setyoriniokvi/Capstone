<?php

namespace App\Http\Controllers\Student;

use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F23 / F25 — pengajuan topik mandiri dan status topik. */
class TopicController extends StudentController
{
    public function index(): View
    {
        $team = $this->team()?->load(['independentTopic.reviewer', 'finalTopic.lecturer', 'supervisor.lecturer', 'applications' => fn ($q) => $q->latest()->with('topic.lecturer')]);

        return view('mahasiswa.topic', ['team' => $team]);
    }

    public function store(Request $request, TopicService $topics): RedirectResponse
    {
        $team = $this->teamOrFail();
        $this->ensureWindow('pengajuan_topik', 'Pengajuan topik mandiri');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:50', 'max:5000'],
            'field' => ['nullable', 'string', 'max:120'],
        ], ['description.min' => 'Uraikan permasalahan minimal 50 karakter.']);

        $topic = $topics->saveIndependent($team, $data, $request->user());

        return back()->with('status', $topic->keyword_hits
            ? 'Topik dikirim ke admin. Catatan: terdeteksi kata kunci yang perlu ditinjau ('.implode(', ', $topic->keyword_hits).').'
            : 'Topik mandiri dikirim ke admin untuk ditinjau.');
    }
}
