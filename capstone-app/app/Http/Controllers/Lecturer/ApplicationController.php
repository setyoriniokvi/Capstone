<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\PrerequisiteCourse;
use App\Models\RuleSpecialization;
use App\Models\Topic;
use App\Models\TopicApplication;
use App\Services\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * F43 — dosen melihat kuota topiknya serta menerima / menolak tim pelamar (alasan penolakan opsional).
 * Pelamar topik bersama masuk ke approval seluruh dosen pengusul; siapa pun boleh memutuskan.
 * Pengajuan aktif dapat disaring menurut peminatan anggota tim; tombol "Detail tim" membuka identitas akademik
 * anggota (asal daerah, SKS lulus, indeks nilai mata kuliah prasyarat, KHS) dan waktu pengajuan.
 */
class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $period = Period::active();
        $peminatan = (string) $request->query('peminatan');
        $base = TopicApplication::query()->whereHas('topic', fn ($q) => $q->proposedBy($request->user()->id))
            ->with(['topic' => fn ($q) => $q->withCount(['applications as accepted_count' => fn ($q) => $q->where('status', 'diterima')]), 'team.members.user', 'team.independentTopic']);
        $ruleOfPeriod = fn ($q) => $q->where('period_id', $period?->id);

        return view('dosen.applications', [
            // Ringkasan kuota seluruh topik milik dosen pada periode aktif
            'topics' => Topic::query()->where('source', 'dosen')->proposedBy($request->user()->id)
                ->when($period, fn ($q, $period) => $q->where('period_id', $period->id))
                ->withCount(['applications as accepted_count' => fn ($q) => $q->where('status', 'diterima')])
                ->orderBy('title')->get(),
            // Tim yang salah satu anggotanya berpeminatan terpilih
            'pending' => (clone $base)->where('status', 'menunggu')
                ->when($peminatan !== '', fn ($q) => $q->whereHas('team.members', fn ($m) => $m->where('invite_status', 'diterima')
                    ->whereHas('user', fn ($u) => $u->where('specialization', $peminatan))))
                ->oldest()->get(),
            'peminatan' => $peminatan,
            'specializations' => RuleSpecialization::query()->whereHas('rule', $ruleOfPeriod)->orderBy('name')->pluck('name')->unique()->values(),
            // Mata kuliah prasyarat periode (indeks nilai anggota disimpan per kode mata kuliah)
            'courses' => PrerequisiteCourse::query()->whereHas('rule', $ruleOfPeriod)->orderBy('code')->get()->unique('code')->values(),
            // Riwayat keputusan: 3 terbaru per halaman (?riwayat=n)
            'decided' => (clone $base)->whereIn('status', ['diterima', 'ditolak'])->latest('decided_at')->latest('id')
                ->paginate(3, ['*'], 'riwayat')->withQueryString(),
        ]);
    }

    public function decide(Request $request, TopicApplication $application, TopicService $topics): RedirectResponse
    {
        abort_unless($application->topic->isProposer($request->user()), 403);
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
