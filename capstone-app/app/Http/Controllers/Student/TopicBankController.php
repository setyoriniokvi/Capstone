<?php

namespace App\Http\Controllers\Student;

use App\Models\Topic;
use App\Models\TopicApplication;
use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** F22 — pengajuan & pembatalan topik dosen (daftar topik ada di tab "Topik Dosen" pada TopicController). */
class TopicBankController extends StudentController
{
    /** Bank topik kini menjadi tab "Topik Dosen" di menu Topik. */
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('mahasiswa.topic.index', ['tab' => 'dosen'] + $request->only('q', 'peminatan'));
    }

    public function apply(Request $request, Topic $topic, TopicService $topics): RedirectResponse
    {
        $team = $this->teamOrFail();
        $this->ensureWindow('apply_topik_dosen', 'Pendaftaran topik dosen');
        $data = $request->validate(
            // Cukup wajib diisi: portofolio boleh di layanan penyimpanan apa pun, tidak dicek formatnya.
            ['portfolio_url' => ['required', 'string', 'max:500']],
            ['portfolio_url.required' => 'Isi tautan portofolio tim.'],
        );
        $topics->apply($team, $topic, $data['portfolio_url']);

        return redirect()->route('mahasiswa.topic.index', ['tab' => 'dosen'])->with('status', "Pengajuan untuk “{$topic->title}” dikirim ke dosen.");
    }

    public function withdraw(TopicApplication $application, TopicService $topics): RedirectResponse
    {
        abort_unless($application->team_id === $this->teamOrFail()->id, 403);
        $topics->withdraw($application);

        return back()->with('status', 'Lamaran dibatalkan.');
    }
}
