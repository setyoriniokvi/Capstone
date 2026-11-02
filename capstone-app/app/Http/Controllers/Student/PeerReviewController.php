<?php

namespace App\Http\Controllers\Student;

use App\Models\PeerReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * F28 — peer review 6 aspek kerja tim (rubrik config/peer_rubric.php, skor 1–5) terhadap rekan satu tim.
 * Setiap rekan dinilai terpisah. Peer review terbuka selama periode aktif berlangsung (tidak terikat
 * Laporan Akhir maupun milestone) dan dapat dikirim ulang berkali-kali; kiriman terbaru menimpa yang lama.
 */
class PeerReviewController extends StudentController
{
    public function index(Request $request): View
    {
        $team = $this->team();
        $teammates = $team ? $team->activeUsers()->whereKeyNot($request->user()->id)->orderBy('name')->get() : collect();
        $existing = $team ? PeerReview::query()->where('reviewer_id', $request->user()->id)->where('team_id', $team->id)->get()
            ->groupBy('reviewee_id')->map(fn ($g) => $g->pluck('score', 'aspect')) : collect();
        $aspects = config('capstone.peer_aspects');

        return view('mahasiswa.peer', [
            'team' => $team,
            'teammates' => $teammates,
            'existing' => $existing,
            // Rekan dianggap "dinilai" bila seluruh aspek sudah diisi.
            'reviewed' => $existing->filter(fn ($scores) => $scores->count() >= count($aspects))->keys(),
            'aspects' => $aspects,
            'rubric' => config('peer_rubric'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $team = $this->teamOrFail();
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

        return back()->with('status', "Penilaian untuk {$name} disimpan. Anda dapat mengubahnya kapan saja selama periode berlangsung.");
    }
}
