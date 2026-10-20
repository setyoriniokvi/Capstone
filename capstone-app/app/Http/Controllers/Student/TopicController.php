<?php

namespace App\Http\Controllers\Student;

use App\Models\Topic;
use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F22 / F23 / F25 — menu Topik mahasiswa dengan dua tab:
 * "Topik Saya" (topik final, alur pengajuan, topik mandiri) dan "Topik Dosen" (bank topik: cari, filter peminatan, ajukan).
 */
class TopicController extends StudentController
{
    public function index(Request $request): View
    {
        $period = $this->period();
        $team = $this->team()?->load([
            'independentTopic.reviewer', 'finalTopic.lecturer.lecturerProfile', 'supervisor.lecturer',
            'applications' => fn ($q) => $q->latest()->with('topic.lecturer'),
        ]);

        // Bank topik dosen; peminatan = kompetensi yang diharapkan pada topik (dipilih dosen dari peminatan prodi periode).
        $all = Topic::query()->published()->where('period_id', $period->id)
            ->with('lecturer.lecturerProfile', 'studyProgram')
            ->withCount(['applications as accepted_count' => fn ($q) => $q->where('status', 'diterima')])
            ->get();
        $specializations = $all->pluck('competencies')->filter()->unique()->sort()->values();

        $topics = Topic::filterBank($all, (string) $request->query('q'), (string) $request->query('peminatan'));

        $independent = $team?->independentTopic;

        return view('mahasiswa.topic', [
            'tab' => $request->query('tab') === 'dosen' ? 'dosen' : 'saya',
            'team' => $team,
            'independent' => $independent,
            'canSubmitIndependent' => $team?->status === 'diterima' && $independent?->status !== 'diterima',
            // Boleh mengajukan beberapa topik selama belum ada yang diterima dosen.
            'canApply' => $team?->status === 'diterima' && $independent?->status === 'diterima'
                && ! $team->applications->contains('status', 'diterima'),
            'topics' => $topics,
            'specializations' => $specializations,
        ]);
    }

    public function store(Request $request, TopicService $topics): RedirectResponse
    {
        $team = $this->teamOrFail();
        $this->ensureWindow('pengajuan_topik', 'Pengajuan topik mandiri');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'object' => ['required', 'string', 'max:255'],
        ], [
            'object.required' => 'Isi objek Capstone (perusahaan, instansi, atau objek lainnya).',
        ]);

        $topic = $topics->saveIndependent($team, $data, $request->user());

        return redirect()->route('mahasiswa.topic.index')->with('status', $topic->keyword_hits
            ? 'Topik dikirim ke admin. Catatan: terdeteksi kata kunci yang perlu ditinjau ('.implode(', ', $topic->keyword_hits).').'
            : 'Topik mandiri dikirim ke admin untuk ditinjau.');
    }
}
