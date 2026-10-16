<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\StudyProgram;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\SystemNotice;
use App\Services\QrService;
use App\Services\ScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** F4 / F6 — data tim, screening otomatis, dan approval registrasi. */
class TeamController extends Controller
{
    public function index(Request $request): View
    {
        $period = Period::active();
        $teams = Team::query()
            ->when($period, fn ($q) => $q->where('period_id', $period->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('prodi'), fn ($q) => $q->where('prodi_code', $request->string('prodi')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', '%'.$request->string('q').'%')->orWhere('name', 'like', '%'.$request->string('q').'%')))
            ->with(['leader', 'members.user'])
            ->orderByRaw("case status when 'menunggu' then 0 when 'ditolak' then 1 when 'draft' then 2 else 3 end")
            ->orderBy('submitted_at')
            ->paginate(25)->withQueryString();

        return view('admin.teams.index', [
            'teams' => $teams,
            'period' => $period,
            'prodis' => StudyProgram::query()->orderBy('code')->get(),
            'counts' => $period ? Team::query()->where('period_id', $period->id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status') : collect(),
        ]);
    }

    public function show(Team $team, QrService $qr): View
    {
        $team->load(['members.user', 'leader', 'finalTopic', 'independentTopic', 'lecturers.lecturer', 'period']);

        return view('admin.teams.show', [
            'team' => $team,
            'qrToken' => $team->status === 'diterima' ? $team->activeQrToken() : null,
            'qr' => $qr,
        ]);
    }

    public function decide(Request $request, Team $team, QrService $qr): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'required_if:decision,reject', 'string', 'max:2000'],
        ], ['notes.required_if' => 'Catatan wajib diisi saat menolak registrasi.']);

        if ($team->status !== 'menunggu') {
            return back()->withErrors(['team' => 'Hanya registrasi berstatus menunggu yang dapat diputuskan.']);
        }

        $approve = $data['decision'] === 'approve';
        $team->update([
            'status' => $approve ? 'diterima' : 'ditolak',
            'review_notes' => $data['notes'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($approve) {
            $qr->ensureToken($team); // F30
        }

        ActivityLog::record($approve ? 'approve_team' : 'reject_team', $team, ['notes' => $data['notes'] ?? null]);
        SystemNotice::sendTo(
            $team->activeUsers,
            $approve ? 'Registrasi tim diterima' : 'Registrasi tim ditolak',
            $approve ? "{$team->code} · {$team->name} resmi terdaftar. Lanjutkan dengan pengajuan topik mandiri." : (string) $data['notes'],
            route('mahasiswa.team.show')
        );

        return redirect()->route('admin.teams.index', ['status' => 'menunggu'])->with('status', "Registrasi {$team->code} ".($approve ? 'diterima.' : 'ditolak.'));
    }

    public function rescreen(Team $team, ScreeningService $screening): RedirectResponse
    {
        $screening->apply($team);

        return back()->with('status', 'Screening diperbarui dengan data terbaru.');
    }

    public function addMember(Request $request, Team $team, ScreeningService $screening): RedirectResponse
    {
        $data = $request->validate(['nim' => ['required', 'string']]);
        $student = User::query()->students()->where('nim', $data['nim'])->first();

        if (! $student) {
            return back()->withErrors(['nim' => 'NIM tidak terdaftar.']);
        }
        if ($student->teamFor($team->period)) {
            return back()->withErrors(['nim' => 'Mahasiswa sudah tergabung di tim lain pada periode ini.']);
        }
        if ($team->members()->where('invite_status', 'diterima')->count() >= config('capstone.team_size.max')) {
            return back()->withErrors(['nim' => 'Tim sudah mencapai jumlah anggota maksimum.']);
        }

        $team->members()->updateOrCreate(['user_id' => $student->id], ['invite_status' => 'diterima', 'responded_at' => now(), 'is_leader' => false]);
        $screening->apply($team);
        ActivityLog::record('admin_add_member', $team, ['nim' => $student->nim]);

        return back()->with('status', "{$student->name} ditambahkan ke tim.");
    }

    public function removeMember(Team $team, TeamMember $member, ScreeningService $screening): RedirectResponse
    {
        abort_unless($member->team_id === $team->id, 404);
        if ($member->is_leader) {
            return back()->withErrors(['member' => 'Ketua tim tidak dapat dikeluarkan. Tetapkan ketua lain terlebih dahulu.']);
        }

        $member->delete();
        // PRD 6.2 — anggota keluar: status kembali menunggu hingga syarat terpenuhi.
        if ($team->status === 'diterima') {
            $team->update(['status' => 'menunggu']);
        }
        $screening->apply($team);
        ActivityLog::record('admin_remove_member', $team, ['user_id' => $member->user_id]);

        return back()->with('status', 'Anggota dikeluarkan dari tim.');
    }

    public function regenerateQr(Team $team, QrService $qr): RedirectResponse
    {
        abort_unless($team->status === 'diterima', 409);
        $qr->regenerate($team);

        return back()->with('status', 'QR Code baru dibuat. QR lama tidak berlaku lagi.');
    }

    public function qrSvg(Team $team, QrService $qr): Response
    {
        $token = $team->activeQrToken() ?? abort(404);

        return response($qr->svg($token, true), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => "attachment; filename=\"qr-{$team->code}.svg\"",
        ]);
    }
}
