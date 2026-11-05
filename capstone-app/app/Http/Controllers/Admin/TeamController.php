<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SystemNotice;
use App\Services\QrService;
use App\Services\ScreeningService;
use App\Services\TeamRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * F4 / F6 — data tim, screening otomatis, approval registrasi, dan CRUD manual tim (tambah, ubah, hapus) oleh admin.
 * Detail tim dan formulir berupa modal di halaman daftar; ?tim={id} membuka detail tim tersebut (tautan dari halaman lain
 * & setelah aksi di dalam modal).
 */
class TeamController extends Controller
{
    /**
     * Daftar registrasi (tanpa draf — tim yang belum diajukan): kartu "Menunggu approval" lalu "Riwayat" keputusan
     * (diterima/ditolak, terbaru dulu, 3 per halaman). Cari & filter syarat screening (di browser) hanya untuk Menunggu approval.
     */
    public function index(Request $request, TeamRegistration $registration): View
    {
        $period = Period::workspace();
        $base = fn () => Team::query()
            ->when($period, fn ($q) => $q->where('period_id', $period->id))
            ->with(['members' => fn ($m) => $m->orderByDesc('is_leader'), 'members.user']);

        // Cari & filter syarat tabel menunggu dilakukan di browser (semua tim menunggu dimuat sekaligus).
        $pending = $base()->where('status', 'menunggu')->orderBy('submitted_at')->get();
        $decided = $base()->whereIn('status', ['diterima', 'ditolak'])
            ->orderByDesc('reviewed_at')->orderByDesc('id')
            ->paginate(3, ['*'], 'riwayat')->withQueryString();

        // Tim yang diminta lewat ?tim= tetapi tidak tampil di halaman/filter ini tetap dibuka detailnya.
        $openTeam = $request->integer('tim');
        $extraTeam = $openTeam && ! $pending->contains('id', $openTeam) && ! $decided->getCollection()->contains('id', $openTeam)
            ? Team::query()->with(['members' => fn ($m) => $m->orderByDesc('is_leader'), 'members.user'])->find($openTeam) : null;

        return view('admin.teams.index', [
            'pending' => $pending,
            'decided' => $decided,
            'period' => $period,
            'openTeam' => $openTeam,
            'extraTeam' => $extraTeam,
            'students' => $period ? $this->availableStudents($period) : collect(),
        ] + $registration->options($period));
    }

    /** Tautan lama ke halaman detail tim: kini membuka modal detail di daftar Registrasi Tim. */
    public function show(Team $team): RedirectResponse
    {
        return redirect()->route('admin.teams.index', ['tim' => $team->id]);
    }

    /** Tambah tim manual oleh admin: semua isian wajib; masuk status menunggu validasi seperti tim hasil impor. */
    public function store(Request $request, ScreeningService $screening): RedirectResponse
    {
        $period = Period::workspace() ?? abort(409, 'Buka dashboard periode terlebih dahulu.');
        $data = $this->validateTeam($request, $period);

        $team = DB::transaction(function () use ($period, $data) {
            $team = Team::create([
                'period_id' => $period->id,
                'name' => $data['name'],
                'leader_id' => $data['members'][0],
                'status' => 'menunggu',
                'submitted_at' => now(),
            ]);
            $this->syncMembers($team, $data['members']);

            return $team;
        });
        $screening->apply($team);
        ActivityLog::record('admin_create_team', $team);

        return redirect()->route('admin.teams.index', ['tim' => $team->id])->with('status', "Tim {$team->name} ditambahkan dan menunggu validasi.");
    }

    /**
     * Ubah tim yang sudah ada (nama, ketua, anggota) — termasuk tim yang sudah diterima; status registrasi tidak berubah.
     * Mahasiswa tidak dapat mengubah timnya sendiri. Tim ditolak sudah dibubarkan sehingga tidak dapat diubah.
     */
    public function update(Request $request, Team $team, ScreeningService $screening): RedirectResponse
    {
        if ($team->status === 'ditolak') {
            return back()->withErrors(['team' => 'Tim yang ditolak sudah dibubarkan dan tidak dapat diubah.']);
        }
        $data = $this->validateTeam($request, $team->period, $team);

        DB::transaction(function () use ($team, $data) {
            $team->update(['name' => $data['name'], 'leader_id' => $data['members'][0]]);
            $this->syncMembers($team, $data['members']);
        });
        $screening->apply($team->fresh());
        ActivityLog::record('admin_update_team', $team);

        return redirect()->route('admin.teams.index', ['tim' => $team->id])->with('status', "Tim {$team->name} diperbarui.");
    }

    /** Hapus tim beserta seluruh datanya (anggota, topik, dokumen, log bimbingan, nilai); akun mahasiswa tetap ada. */
    public function destroy(Team $team): RedirectResponse
    {
        $name = $team->name;
        DB::transaction(function () use ($team) {
            ActivityLog::record('admin_delete_team', $team, ['name' => $team->name]);
            $team->delete();
        });
        Storage::disk('local')->deleteDirectory("registrations/{$team->id}");

        return redirect()->route('admin.teams.index')->with('status', "Tim {$name} dihapus.");
    }

    /**
     * Isian tim admin: nama + mahasiswa (indeks 0 = ketua). Pilihan yang dikosongkan diabaikan; mahasiswa harus aktif dan
     * belum bertim di periode ini (kecuali anggota tim yang sedang diubah).
     *
     * @return array{name: string, members: list<int>}
     */
    private function validateTeam(Request $request, Period $period, ?Team $team = null): array
    {
        ['min' => $min, 'max' => $max] = config('capstone.team_size');
        $available = $this->availableStudents($period)->pluck('id')->merge($team?->members->pluck('user_id') ?? [])->all();
        $request->merge(['members' => collect($request->input('members', []))->filter(fn ($id) => filled($id))->values()->all()]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'members' => ['required', 'array', "min:{$min}", "max:{$max}"],
            'members.*' => ['required', 'integer', 'distinct', Rule::in($available)],
        ], [
            'members.required' => "Tim terdiri dari minimal {$min} mahasiswa (termasuk ketua).",
            'members.min' => "Tim terdiri dari minimal {$min} mahasiswa (termasuk ketua).",
            'members.*.distinct' => 'Mahasiswa yang sama dipilih lebih dari sekali.',
            'members.*.in' => 'Mahasiswa sudah tergabung di tim lain pada periode ini atau tidak aktif.',
        ]);
        $taken = Team::query()->where('period_id', $period->id)->where('name', $data['name'])->where('status', '!=', 'ditolak')
            ->when($team, fn ($q) => $q->whereKeyNot($team->id))->exists();
        if ($taken) {
            throw ValidationException::withMessages(['name' => 'Nama tim sudah dipakai pada periode ini.']);
        }

        return ['name' => $data['name'], 'members' => array_map('intval', $data['members'])];
    }

    /** Samakan anggota tim dengan daftar (indeks 0 = ketua); anggota yang dikeluarkan beserta lampirannya dihapus. */
    private function syncMembers(Team $team, array $ids): void
    {
        $team->members()->whereNotIn('user_id', $ids)->get()->each(function ($member) {
            Storage::disk('local')->delete(array_filter([$member->khs_path, $member->domicile_path]));
            $member->delete();
        });
        foreach ($ids as $i => $id) {
            $team->members()->updateOrCreate(['user_id' => $id], ['is_leader' => $i === 0, 'invite_status' => 'diterima', 'responded_at' => now()]);
        }
    }

    public function decide(Request $request, Team $team, QrService $qr, TeamRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($team->status !== 'menunggu') {
            return back()->withErrors(['team' => 'Hanya registrasi berstatus menunggu yang dapat diputuskan.']);
        }

        $approve = $data['decision'] === 'approve';
        $mail = $this->applyDecision($team, $approve, $data['notes'] ?? null, $request->user(), $qr, $registration);

        return redirect()->route('admin.teams.index')->with('status', "Registrasi {$team->name} ".($approve ? 'diterima.' : 'ditolak.').' '.$this->mailSummary($mail));
    }

    /** Terima sekaligus tim yang dicentang di tabel "Menunggu Approval"; tim yang sudah diputuskan dilewati. */
    public function approveMany(Request $request, QrService $qr, TeamRegistration $registration): RedirectResponse
    {
        return $this->decideMany($request, true, $qr, $registration);
    }

    /** Tolak sekaligus tim yang dicentang; catatan (opsional) dikirim ke anggota setiap tim. */
    public function rejectMany(Request $request, QrService $qr, TeamRegistration $registration): RedirectResponse
    {
        return $this->decideMany($request, false, $qr, $registration);
    }

    private function decideMany(Request $request, bool $approve, QrService $qr, TeamRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'teams' => ['required', 'array', 'min:1'],
            'teams.*' => ['integer', 'distinct'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['teams.required' => 'Pilih minimal satu tim.']);

        $teams = Team::query()->whereIn('id', $data['teams'])->where('status', 'menunggu')->orderBy('name')->get();
        if ($teams->isEmpty()) {
            return back()->withErrors(['team' => 'Tidak ada tim terpilih yang menunggu keputusan.']);
        }

        $mail = ['sent' => 0, 'failed' => 0];
        foreach ($teams as $team) {
            $result = $this->applyDecision($team, $approve, $approve ? null : ($data['notes'] ?? null), $request->user(), $qr, $registration);
            $mail['sent'] += $result['sent'];
            $mail['failed'] += $result['failed'];
        }

        return redirect()->route('admin.teams.index')->with('status', $teams->count().' registrasi tim '.($approve ? 'diterima' : 'ditolak').'. '.$this->mailSummary($mail));
    }

    /**
     * F6 — simpan keputusan, notifikasi dalam aplikasi, dan email keputusan ke setiap anggota (F21; anggota yang akunnya
     * dibuat ketua mendapat tautan atur password). Ditolak: tim dibubarkan setelah pemberitahuan terkirim; yang tersisa
     * hanya log pendaftar untuk admin.
     *
     * @return array{sent: int, failed: int}
     */
    private function applyDecision(Team $team, bool $approve, ?string $notes, User $admin, QrService $qr, TeamRegistration $registration): array
    {
        $team->update([
            'status' => $approve ? 'diterima' : 'ditolak',
            'review_notes' => $notes,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
        ]);

        if ($approve) {
            $qr->ensureToken($team); // F30
        }
        ActivityLog::record($approve ? 'approve_team' : 'reject_team', $team, ['notes' => $notes]);
        SystemNotice::sendTo(
            $team->activeUsers,
            $approve ? 'Registrasi tim diterima' : 'Registrasi tim ditolak',
            $approve ? "{$team->name} resmi terdaftar. Lanjutkan dengan pengajuan topik mandiri dan/atau topik dosen." : 'Alasan: '.($notes ?: 'tidak ada catatan dari admin.').' Silakan lakukan registrasi ulang.',
            route('mahasiswa.team.show')
        );
        $mail = $registration->notifyDecision($team, $approve, $notes);
        if (! $approve) {
            $registration->dissolve($team);
        }

        return $mail;
    }

    private function mailSummary(array $mail): string
    {
        return "Email terkirim ke {$mail['sent']} anggota."
            .($mail['failed'] ? " {$mail['failed']} email gagal dikirim — periksa pengaturan email (SMTP)." : '');
    }

    /** Kirim ulang tautan atur password untuk anggota tim diterima yang akunnya belum aktif (tautan kedaluwarsa/hilang). */
    public function resendActivation(Team $team, TeamRegistration $registration): RedirectResponse
    {
        abort_unless($team->status === 'diterima', 409);
        $mail = $registration->resendActivation($team);

        return redirect()->route('admin.teams.index', ['tim' => $team->id])
            ->with('status', $mail['sent'] + $mail['failed'] ? 'Tautan atur password: '.$this->mailSummary($mail) : 'Semua anggota sudah mengatur password.');
    }

    /** Mahasiswa aktif yang belum tergabung (undangan diterima) di tim mana pun pada periode ini. */
    private function availableStudents(Period $period): Collection
    {
        return User::query()->students()->where('is_active', true)
            ->whereDoesntHave('memberships', fn ($q) => $q->where('invite_status', 'diterima')
                ->whereHas('team', fn ($t) => $t->where('period_id', $period->id)))
            ->orderBy('name')->get(['id', 'name', 'nim']);
    }
}
