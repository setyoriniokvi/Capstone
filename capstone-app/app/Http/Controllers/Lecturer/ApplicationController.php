<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\Topic;
use App\Models\TopicApplication;
use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F43 — dosen melihat kuota topiknya serta menerima / menolak tim pelamar (alasan penolakan opsional). */
class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $base = TopicApplication::query()->whereHas('topic', fn ($q) => $q->where('lecturer_id', $request->user()->id))
            ->with(['topic' => fn ($q) => $q->withCount(['applications as accepted_count' => fn ($q) => $q->where('status', 'diterima')]), 'team.members.user', 'team.independentTopic']);

        return view('dosen.applications', [
            // Ringkasan kuota seluruh topik milik dosen pada periode aktif
            'topics' => Topic::query()->where('source', 'dosen')->where('lecturer_id', $request->user()->id)
                ->when(Period::active(), fn ($q, $period) => $q->where('period_id', $period->id))
                ->withCount(['applications as accepted_count' => fn ($q) => $q->where('status', 'diterima')])
                ->orderBy('title')->get(),
            'pending' => (clone $base)->where('status', 'menunggu')->oldest()->get(),
            // Riwayat keputusan: 3 terbaru per halaman (?riwayat=n)
            'decided' => (clone $base)->whereIn('status', ['diterima', 'ditolak'])->latest('decided_at')->latest('id')
                ->paginate(3, ['*'], 'riwayat')->withQueryString(),
        ]);
    }

    public function decide(Request $request, TopicApplication $application, TopicService $topics): RedirectResponse
    {
        abort_unless($application->topic->lecturer_id === $request->user()->id, 403);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $accept = $data['decision'] === 'approve';
        $topics->decide($application, $accept, $data['notes'] ?? null, $request->user());

        return back()->with('status', $accept
            ? "{$application->team->name} diterima. Anda otomatis tercatat sebagai pembimbing tim ini."
            : "Lamaran {$application->team->name} ditolak.");
    }
}
