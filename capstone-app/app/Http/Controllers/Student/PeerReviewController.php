<?php

namespace App\Http\Controllers\Student;

use App\Models\PeerReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * F28 — peer review 6 aspek kerja tim (rubrik config/peer_rubric.php, skor 1–5) terhadap rekan satu tim.
 * Setiap rekan dinilai terpisah dan dapat ditimpa selama jendela peer review dibuka.
 */
class PeerReviewController extends StudentController
{
    public function index(Request $request): View
    {
        $team = $this->team();
        $teammates = $team ? $team->activeUsers()->whereKeyNot($request->user()->id)->with('studyProgram')->orderBy('name')->get() : collect();
        $existing = $team ? PeerReview::query()->where('reviewer_id', $request->user()->id)->where('team_id', $team->id)->get()
            ->groupBy('reviewee_id')->map(fn ($g) => $g->pluck('score', 'aspect')) : collect();
        $aspects = config('capstone.peer_aspects');

        $windows = $team ? $team->period->milestones()->where('kind', 'peer_review')->get() : collect();

        return view('mahasiswa.peer', [
            'team' => $team,
            'teammates' => $teammates,
            'existing' => $existing,
            // Rekan dianggap "dinilai" bila seluruh aspek sudah diisi.
            'reviewed' => $existing->filter(fn ($scores) => $scores->count() >= count($aspects))->keys(),
            'aspects' => $aspects,
            'rubric' => config('peer_rubric'),
            'windowOpen' => $windows->isEmpty() || $windows->contains(fn ($m) => $m->isOpen()),
            'windowNote' => $windows->isEmpty() ? null : $windows->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->teamOrFail();
        $this->ensureWindow('peer_review', 'Peer review');
        $teammates = $team->activeUsers()->whereKeyNot($request->user()->id)->pluck('users.id');
        $aspects = array_keys(config('capstone.peer_aspects'));

        $rules = ['reviewee_id' => ['required', 'integer', 'in:'.$teammates->implode(',')]];
        foreach ($aspects as $aspect) {
            $rules["scores.{$aspect}"] = ['required', 'integer', 'between:1,5'];
        }
        $data = $request->validate($rules, [
            'reviewee_id.in' => 'Anggota tersebut bukan rekan satu tim Anda.',
            'scores.*.required' => 'Lengkapi semua aspek penilaian.',
        ]);

        DB::transaction(function () use ($data, $team, $request, $aspects) {
            foreach ($aspects as $aspect) {
                PeerReview::query()->updateOrCreate(
                    ['reviewer_id' => $request->user()->id, 'reviewee_id' => $data['reviewee_id'], 'aspect' => $aspect],
                    ['team_id' => $team->id, 'score' => $data['scores'][$aspect]]
                );
            }
        });

        $name = $team->activeUsers()->whereKey($data['reviewee_id'])->value('name');

        return back()->with('status', "Penilaian untuk {$name} disimpan. Anda dapat mengubahnya selama periode peer review dibuka.");
    }
}
