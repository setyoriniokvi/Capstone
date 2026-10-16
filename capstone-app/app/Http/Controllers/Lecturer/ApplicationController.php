<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\TopicApplication;
use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F43 — dosen menyetujui / menolak tim pelamar topiknya. */
class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $base = TopicApplication::query()->whereHas('topic', fn ($q) => $q->where('lecturer_id', $request->user()->id))
            ->with(['topic' => fn ($q) => $q->withCount(['applications as accepted_count' => fn ($q) => $q->where('status', 'diterima')]), 'team.members.user', 'team.independentTopic']);

        return view('dosen.applications', [
            'pending' => (clone $base)->where('status', 'menunggu')->oldest()->get(),
            'decided' => (clone $base)->whereIn('status', ['diterima', 'ditolak'])->latest('decided_at')->limit(30)->get(),
        ]);
    }

    public function decide(Request $request, TopicApplication $application, TopicService $topics): RedirectResponse
    {
        abort_unless($application->topic->lecturer_id === $request->user()->id, 403);
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'required_if:decision,reject', 'string', 'max:2000'],
        ], ['notes.required_if' => 'Catatan wajib diisi saat menolak lamaran.']);

        $accept = $data['decision'] === 'approve';
        $topics->decide($application, $accept, $data['notes'] ?? null, $request->user());

        return back()->with('status', $accept
            ? "{$application->team->code} diterima. Anda otomatis tercatat sebagai pembimbing tim ini."
            : "Lamaran {$application->team->code} ditolak.");
    }
}
