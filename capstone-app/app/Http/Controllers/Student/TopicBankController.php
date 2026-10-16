<?php

namespace App\Http\Controllers\Student;

use App\Models\Topic;
use App\Models\TopicApplication;
use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F22 — bank topik dosen: cari, filter, dan lamar. */
class TopicBankController extends StudentController
{
    public function index(Request $request): View
    {
        $period = $this->period();
        $topics = Topic::query()->published()->where('period_id', $period->id)
            ->with('lecturer.lecturerProfile')
            ->withCount(['applications as accepted_count' => fn ($q) => $q->where('status', 'diterima')])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('description', 'like', $term)
                    ->orWhereHas('lecturer', fn ($l) => $l->where('name', 'like', $term)));
            })
            ->when($request->filled('bidang'), fn ($q) => $q->where('field', $request->string('bidang')))
            ->orderBy('title')
            ->get()
            ->when($request->boolean('tersedia'), fn ($c) => $c->filter(fn ($t) => $t->quota > $t->accepted_count))
            ->values();

        $team = $this->team();

        return view('mahasiswa.bank', [
            'topics' => $topics,
            'fields' => Topic::query()->published()->where('period_id', $period->id)->whereNotNull('field')->distinct()->orderBy('field')->pluck('field'),
            'team' => $team,
            'applications' => $team ? $team->applications()->with('topic')->latest()->get() : collect(),
            'canApply' => $team?->status === 'diterima' && $team->independentTopic?->status === 'diterima',
        ]);
    }

    public function apply(Request $request, Topic $topic, TopicService $topics): RedirectResponse
    {
        $team = $this->teamOrFail();
        $this->ensureWindow('apply_topik_dosen', 'Pendaftaran topik dosen');
        $data = $request->validate(['motivation' => ['nullable', 'string', 'max:2000']]);
        $topics->apply($team, $topic, $data['motivation'] ?? null);

        return back()->with('status', "Lamaran untuk “{$topic->title}” dikirim ke dosen.");
    }

    public function withdraw(TopicApplication $application, TopicService $topics): RedirectResponse
    {
        abort_unless($application->team_id === $this->teamOrFail()->id, 403);
        $topics->withdraw($application);

        return back()->with('status', 'Lamaran dibatalkan.');
    }
}
