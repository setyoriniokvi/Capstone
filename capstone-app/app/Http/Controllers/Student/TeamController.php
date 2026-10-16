<?php

namespace App\Http\Controllers\Student;

use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\SystemNotice;
use App\Services\ScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** F21 / F24 — registrasi tim, undangan anggota, kelengkapan data, dan status. */
class TeamController extends StudentController
{
    public function show(Request $request, ScreeningService $screening): View
    {
        $user = $request->user();
        $period = $this->period()->load('registrationRules.courses');
        $team = $user->teamFor($period)?->load(['members.user', 'leader', 'reviewer']);
        $rule = $period->registrationRules->firstWhere('prodi_code', $user->prodi_code);

        return view('mahasiswa.team', [
            'period' => $period,
            'team' => $team,
            'me' => $team?->members->firstWhere('user_id', $user->id),
            'preview' => $team ? $screening->evaluate($team) : null,
            'eligibility' => $screening->eligibility($user, $rule),
            'invitations' => $user->memberships()->where('invite_status', 'diundang')
                ->whereHas('team', fn ($q) => $q->where('period_id', $period->id))->with('team.leader')->get(),
            'editable' => $team && in_array($team->status, ['draft', 'ditolak'], true),
            'isLeader' => $team && $team->leader_id === $user->id,
        ]);
    }

    public function store(Request $request, ScreeningService $screening): RedirectResponse
    {
        $user = $request->user();
        $period = $this->period();
        $this->ensureWindow('registrasi_tim', 'Registrasi tim');
        $data = $request->validate(['name' => ['required', 'string', 'max:150']]);

        if ($user->teamFor($period)) {
            return back()->withErrors(['name' => 'Anda sudah tergabung dalam tim pada periode ini.']);
        }
        $this->ensureEligible($user, $screening);

        DB::transaction(function () use ($user, $period, $data) {
            $team = Team::create([
                'period_id' => $period->id,
                'code' => $this->nextCode($period->id, $user->prodi_code),
                'name' => $data['name'],
                'prodi_code' => $user->prodi_code,
                'leader_id' => $user->id,
                'status' => 'draft',
            ]);
            // Undangan lain yang masih tertunda dianggap ditolak.
            $user->memberships()->where('invite_status', 'diundang')->update(['invite_status' => 'ditolak', 'responded_at' => now()]);
            $team->members()->create(['user_id' => $user->id, 'is_leader' => true, 'invite_status' => 'diterima', 'responded_at' => now()]);
            ActivityLog::record('create_team', $team);
        });

        return redirect()->route('mahasiswa.team.show')->with('status', 'Tim dibuat. Undang anggota dan lengkapi data registrasi.');
    }

    public function update(Request $request): RedirectResponse
    {
        $team = $this->editableTeam(leaderOnly: true);
        $team->update($request->validate(['name' => ['required', 'string', 'max:150']]));

        return back()->with('status', 'Nama tim diperbarui.');
    }

    public function invite(Request $request): RedirectResponse
    {
        $team = $this->editableTeam(leaderOnly: true);
        $data = $request->validate(['identifier' => ['required', 'string', 'max:150']], ['identifier.required' => 'Isi NIM atau email anggota.']);
        $identifier = Str::lower(trim($data['identifier']));

        $student = User::query()->students()->where('is_active', true)
            ->where(fn ($q) => $q->where('nim', $identifier)->orWhere('email', $identifier))->first();

        $error = match (true) {
            ! $student => 'NIM atau email tidak terdaftar sebagai mahasiswa.',
            $student->id === $request->user()->id => 'Anda sudah menjadi ketua tim ini.',
            (bool) $student->teamFor($team->period) => 'Mahasiswa tersebut sudah tergabung di tim lain.',
            $team->members()->whereIn('invite_status', ['diundang', 'diterima'])->count() >= config('capstone.team_size.max') => 'Tim sudah mencapai '.config('capstone.team_size.max').' anggota.',
            default => null,
        };
        if ($error) {
            return back()->withErrors(['identifier' => $error]);
        }

        $team->members()->updateOrCreate(['user_id' => $student->id], ['invite_status' => 'diundang', 'is_leader' => false, 'responded_at' => null]);
        SystemNotice::sendTo([$student], 'Undangan bergabung ke tim', "{$request->user()->name} mengundang Anda ke tim {$team->name}.", route('mahasiswa.team.show'));

        return back()->with('status', "Undangan dikirim ke {$student->name}.");
    }

    public function removeMember(Request $request, TeamMember $member): RedirectResponse
    {
        $team = $this->editableTeam();
        abort_unless($member->team_id === $team->id, 404);
        $isSelf = $member->user_id === $request->user()->id;

        if ($member->is_leader) {
            return back()->withErrors(['member' => 'Ketua tidak dapat keluar dari tim. Hubungi admin bila perlu mengganti ketua.']);
        }
        if (! $isSelf && $team->leader_id !== $request->user()->id) {
            abort(403);
        }

        $this->deleteAttachments($member);
        $member->delete();

        return $isSelf
            ? redirect()->route('mahasiswa.team.show')->with('status', 'Anda keluar dari tim.')
            : back()->with('status', 'Anggota dikeluarkan dari tim.');
    }

    public function respond(Request $request, TeamMember $member, ScreeningService $screening): RedirectResponse
    {
        abort_unless($member->user_id === $request->user()->id && $member->invite_status === 'diundang', 404);
        $accept = $request->input('decision') === 'accept';

        if ($accept) {
            if ($request->user()->teamFor($member->team->period)) {
                return back()->withErrors(['invite' => 'Anda sudah tergabung di tim lain.']);
            }
            if (! in_array($member->team->status, ['draft', 'ditolak'], true)) {
                return back()->withErrors(['invite' => 'Tim tersebut sudah mengajukan registrasi.']);
            }
            $this->ensureEligible($request->user(), $screening);
        }

        DB::transaction(function () use ($member, $accept, $request) {
            $member->update(['invite_status' => $accept ? 'diterima' : 'ditolak', 'responded_at' => now()]);
            if ($accept) {
                $request->user()->memberships()->where('invite_status', 'diundang')->whereKeyNot($member->id)
                    ->update(['invite_status' => 'ditolak', 'responded_at' => now()]);
            }
        });

        SystemNotice::sendTo([$member->team->leader], $accept ? 'Undangan diterima' : 'Undangan ditolak', "{$request->user()->name} ".($accept ? 'bergabung ke' : 'menolak undangan').' tim Anda.', route('mahasiswa.team.show'));

        return redirect()->route('mahasiswa.team.show')->with('status', $accept ? 'Anda bergabung ke tim.' : 'Undangan ditolak.');
    }

    /** Data peminatan, daerah asal, lampiran KHS & domisili milik anggota sendiri. */
    public function profile(Request $request): RedirectResponse
    {
        $team = $this->editableTeam();
        $member = $team->members()->where('user_id', $request->user()->id)->firstOrFail();
        $mimes = implode(',', config('capstone.upload.attachment_mimes'));
        $max = config('capstone.upload.attachment_max_kb');

        $data = $request->validate([
            'specialization' => ['required', 'string', 'max:100'],
            'origin_region' => ['required', 'string', 'max:100'],
            'khs' => [$member->khs_path ? 'nullable' : 'required', 'file', "mimes:{$mimes}", "max:{$max}"],
            'domicile' => [$member->domicile_path ? 'nullable' : 'required', 'file', "mimes:{$mimes}", "max:{$max}"],
        ], [
            'khs.required' => 'Unggah KHS terbaru.',
            'domicile.required' => 'Unggah bukti domisili.',
        ]);

        $request->user()->update(['specialization' => trim($data['specialization']), 'origin_region' => trim($data['origin_region'])]);

        foreach (['khs' => 'khs_path', 'domicile' => 'domicile_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                if ($member->{$column}) {
                    Storage::disk('local')->delete($member->{$column});
                }
                $member->{$column} = $request->file($input)->store("registrations/{$team->id}/{$member->user_id}", 'local');
            }
        }
        $member->save();

        return back()->with('status', 'Data registrasi Anda disimpan.');
    }

    public function submit(Request $request, ScreeningService $screening): RedirectResponse
    {
        $team = $this->editableTeam(leaderOnly: true);
        $this->ensureWindow('registrasi_tim', 'Registrasi tim');
        $team->load('members.user');

        $active = $team->members->where('invite_status', 'diterima');
        $min = config('capstone.team_size.min');
        $max = config('capstone.team_size.max');

        $error = match (true) {
            $team->members->contains('invite_status', 'diundang') => 'Masih ada undangan yang belum dijawab. Tunggu atau batalkan undangan tersebut.',
            $active->count() < $min || $active->count() > $max => "Tim harus beranggotakan {$min}–{$max} orang.",
            $active->contains(fn ($m) => ! $m->khs_path || ! $m->domicile_path) => 'Seluruh anggota wajib mengunggah KHS dan bukti domisili.',
            $active->contains(fn ($m) => blank($m->user->specialization) || blank($m->user->origin_region)) => 'Seluruh anggota wajib mengisi peminatan dan daerah asal.',
            default => null,
        };
        if ($error) {
            return back()->withErrors(['team' => $error]);
        }

        $team->update(['status' => 'menunggu', 'submitted_at' => now()]);
        $result = $screening->apply($team);
        ActivityLog::record('submit_team', $team, ['score' => $result['score']]);
        SystemNotice::sendTo($team->activeUsers()->whereKeyNot($request->user()->id)->get(), 'Registrasi tim diajukan', "{$team->code} menunggu validasi admin.", route('mahasiswa.team.show'));

        return back()->with('status', 'Registrasi tim diajukan ke admin. Skor screening otomatis: '.$result['score'].'/100.');
    }

    private function editableTeam(bool $leaderOnly = false): Team
    {
        $team = $this->team() ?? throw ValidationException::withMessages(['team' => 'Anda belum tergabung dalam tim.']);
        if (! in_array($team->status, ['draft', 'ditolak'], true)) {
            throw ValidationException::withMessages(['team' => 'Data tim tidak dapat diubah karena registrasi sedang diproses atau sudah diterima.']);
        }
        if ($leaderOnly && $team->leader_id !== auth()->id()) {
            throw ValidationException::withMessages(['team' => 'Hanya ketua tim yang dapat melakukan tindakan ini.']);
        }

        return $team;
    }

    /** PRD 6.1 — akses pembentukan kelompok ditunda bila prasyarat belum terpenuhi. */
    private function ensureEligible(User $user, ScreeningService $screening): void
    {
        $rule = $this->period()->registrationRules()->with('courses')->where('prodi_code', $user->prodi_code)->first();
        $failed = collect($screening->eligibility($user, $rule))->where('passed', false);
        if ($failed->isNotEmpty()) {
            throw ValidationException::withMessages(['team' => 'Anda belum memenuhi prasyarat Capstone: '.$failed->pluck('label')->implode('; ').'.']);
        }
    }

    private function nextCode(int $periodId, ?string $prodi): string
    {
        $prefix = 'KP-'.($prodi ?: 'FRI').'-';
        $last = Team::query()->where('period_id', $periodId)->where('code', 'like', $prefix.'%')->pluck('code')
            ->map(fn ($c) => (int) Str::after($c, $prefix))->max() ?? 0;

        do {
            $code = $prefix.str_pad((string) ++$last, 2, '0', STR_PAD_LEFT);
        } while (Team::query()->where('code', $code)->exists());

        return $code;
    }

    private function deleteAttachments(TeamMember $member): void
    {
        Storage::disk('local')->delete(array_filter([$member->khs_path, $member->domicile_path]));
    }
}
