<?php

namespace App\Http\Controllers\Student;

use App\Models\PeerReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** F28 — peer review 6 aspek terhadap rekan satu tim (dapat ditimpa). */
class PeerReviewController extends StudentController
{
    public function index(Request $request): View
    {
        $team = $this->team();
        $teammates = $team ? $team->activeUsers()->whereKeyNot($request->user()->id)->orderBy('name')->get() : collect();
        $existing = $team ? PeerReview::query()->where('reviewer_id', $request->user()->id)->where('team_id', $team->id)->get()
            ->groupBy('reviewee_id')->map(fn ($g) => $g->pluck('score', 'aspect')) : collect();

        return view('mahasiswa.peer', [
            'team' => $team,
            'teammates' => $teammates,
            'existing' => $existing,
            'aspects' => config('capstone.peer_aspects'),
            'rubric' => config('peer_rubric'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->teamOrFail();
        $this->ensureWindow('peer_review', 'Peer review');
        $teammates = $team->activeUsers()->whereKeyNot($request->user()->id)->pluck('users.id');
        $aspects = array_keys(config('capstone.peer_aspects'));

        $rules = [];
        foreach ($teammates as $id) {
            foreach ($aspects as $aspect) {
                $rules["scores.{$id}.{$aspect}"] = ['required', 'integer', 'between:1,5'];
            }
        }
        $data = $request->validate($rules, ['scores.*.*.required' => 'Lengkapi semua aspek untuk setiap rekan.']);

        DB::transaction(function () use ($data, $team, $request, $teammates, $aspects) {
            foreach ($teammates as $id) {
                foreach ($aspects as $aspect) {
                    PeerReview::query()->updateOrCreate(
                        ['reviewer_id' => $request->user()->id, 'reviewee_id' => $id, 'aspect' => $aspect],
                        ['team_id' => $team->id, 'score' => $data['scores'][$id][$aspect]]
                    );
                }
            }
        });

        return back()->with('status', 'Peer review disimpan. Anda dapat mengubahnya selama periode peer review dibuka.');
    }
}
