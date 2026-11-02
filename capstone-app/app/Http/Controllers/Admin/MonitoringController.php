<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Models\ReminderDispatch;
use App\Models\Team;
use App\Models\User;
use App\Services\ReminderService;
use App\Services\TeamRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** F11 progres tim & dosen; F12 monitoring penilaian + reminder. */
class MonitoringController extends Controller
{
    /**
     * Monitoring progres tim (registrasi diterima): nama tim, topik final + asalnya (dosen/mandiri), dosen pembimbing &
     * penguji, log bimbingan, dan "Detail tim" (anggota, dokumen per milestone). Cari nama tim / nama anggota / judul
     * topik final; filter peminatan anggota tim.
     */
    public function progress(Request $request, TeamRegistration $registration): View
    {
        $period = Period::workspace()?->load('milestones');
        $peminatan = (string) $request->query('peminatan');
        $term = '%'.$request->string('q').'%';
        $teams = $period ? Team::query()->where('period_id', $period->id)->where('status', 'diterima')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', $term)
                ->orWhereHas('members.user', fn ($u) => $u->where('name', 'like', $term))
                ->orWhereHas('finalTopic', fn ($t) => $t->where('title', 'like', $term))))
            ->when($peminatan !== '', fn ($q) => $q->whereHas('members', fn ($m) => $m->where('invite_status', 'diterima')
                ->whereHas('user', fn ($u) => $u->where('specialization', $peminatan))))
            ->with(['finalTopic', 'supervisor.lecturer.lecturerProfile', 'examiner.lecturer.lecturerProfile',
                'members' => fn ($m) => $m->where('invite_status', 'diterima')->orderByDesc('is_leader'), 'members.user'])
            ->withCount([
                'logbooks as logbook_total',
                'logbooks as logbook_approved' => fn ($q) => $q->where('status', 'disetujui'),
                'logbooks as logbook_pending' => fn ($q) => $q->where('status', 'menunggu'),
                'logbooks as logbook_revision' => fn ($q) => $q->where('status', 'revisi'),
            ])
            ->with(['documents' => fn ($q) => $q->where('state', 'aktif')->select('id', 'team_id', 'milestone_id', 'is_late')])
            ->orderBy('name')
            ->paginate(25)->withQueryString() : null;

        return view('admin.monitoring.progress', [
            'period' => $period,
            'teams' => $teams,
            'peminatan' => $peminatan,
            'specializations' => $registration->options($period)['specializations'],
            'docMilestones' => $period?->milestones->where('kind', 'dokumen')->values() ?? collect(),
            'minLogbooks' => Period::minLogbooks($period),
        ]);
    }

    public function assessments(Request $request, ReminderService $reminders): View
    {
        $period = Period::workspace();
        $rows = $period ? $reminders->incompleteAssessments($period) : collect();

        $byLecturer = $rows->groupBy(fn ($r) => $r['lecturer']->id)->map(function ($items) {
            $lecturer = $items->first()['lecturer'];
            $missing = $items->where('done', false);

            return [
                'lecturer' => $lecturer,
                'teams' => $items->pluck('team')->unique('id')->values(),
                'missing' => $missing->groupBy('component')->map->count(),
                'roles' => $items->pluck('role')->unique()->values(),
                'complete' => $missing->isEmpty(),
            ];
        })
            ->when($request->filled('peran'), fn ($c) => $c->filter(fn ($r) => $r['roles']->contains($request->input('peran'))))
            ->when($request->input('status') === 'belum', fn ($c) => $c->reject(fn ($r) => $r['complete']))
            ->when($request->input('status') === 'lengkap', fn ($c) => $c->filter(fn ($r) => $r['complete']))
            ->sortBy(fn ($r) => [$r['complete'], $r['lecturer']->name])
            ->values();

        return view('admin.monitoring.assessments', [
            'period' => $period,
            'rows' => $byLecturer,
            'lastSent' => ReminderDispatch::query()->where('trigger', 'manual')->selectRaw('lecturer_id, max(created_at) as at')->groupBy('lecturer_id')->pluck('at', 'lecturer_id'),
        ]);
    }

    public function remind(Request $request, ReminderService $reminders): RedirectResponse
    {
        $data = $request->validate([
            'lecturer_ids' => ['required', 'array', 'min:1'],
            'lecturer_ids.*' => [Rule::exists('users', 'id')->where('role', 'dosen')],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,whatsapp'],
        ], [
            'lecturer_ids.required' => 'Pilih minimal satu dosen.',
            'channels.required' => 'Pilih minimal satu kanal pengiriman.',
        ]);

        $count = $reminders->queueManual(User::query()->whereIn('id', $data['lecturer_ids'])->get(), $data['channels'], $request->user());

        return back()->with('status', $count
            ? "{$count} pengingat masuk antrean pengiriman. Status kirim tercatat di halaman Notifikasi Otomatis."
            : 'Tidak ada komponen nilai yang belum diisi untuk dosen terpilih.');
    }
}
