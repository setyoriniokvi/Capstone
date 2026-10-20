<?php

namespace App\Http\Controllers\Student;

use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\SystemNotice;
use App\Services\ScreeningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * F21 / F24 — halaman Tim Saya dan registrasi tim.
 * Ketua mengisi satu formulir untuk seluruh tim (nama tim, anggota dari daftar mahasiswa,
 * daerah asal & KHS tiap anggota); pengajuan langsung masuk antrean validasi admin.
 */
class TeamController extends StudentController
{
    public function show(Request $request, ScreeningService $screening): View
    {
        $user = $request->user();
        $period = $this->period()->load('registrationRule.courses');
        $team = $user->teamFor($period)?->load(['members.user', 'leader']);
        $rule = $period->registrationRule;
        // Ketua dapat mengubah registrasi selama belum diterima admin (draf, menunggu, ditolak).
        $editable = $team && in_array($team->status, ['draft', 'menunggu', 'ditolak'], true) && $team->leader_id === $user->id;

        return view('mahasiswa.team', [
            'period' => $period,
            'team' => $team,
            'members' => $team?->members->where('invite_status', 'diterima')->values() ?? collect(),
            'screening' => $team ? $screening->summary($team) : null,
            'eligibility' => $screening->eligibility($user, $rule),
            'editable' => $editable,
            // Pilihan anggota hanya dimuat bila formulir dapat dibuka.
            'students' => ! $team || $editable ? $this->availableStudents($user, $period, $team) : collect(),
            'teamSize' => config('capstone.team_size'),
        ]);
    }

    public function store(Request $request, ScreeningService $screening): RedirectResponse
    {
        $user = $request->user();
        $period = $this->period();
        $this->ensureWindow('registrasi_tim', 'Registrasi tim');

        if ($user->teamFor($period)) {
            throw ValidationException::withMessages(['team' => 'Anda sudah tergabung dalam tim pada periode ini.']);
        }
        $this->ensureEligible($user, $screening);
        $data = $this->validateRegistration($request, $period, $screening, null);

        $team = DB::transaction(function () use ($user, $period, $data, $request) {
            $team = Team::create([
                'period_id' => $period->id,
                'code' => $this->nextCode($period->id, $user->prodi_code),
                'name' => $data['name'],
                'prodi_code' => $user->prodi_code,
                'leader_id' => $user->id,
                'status' => 'menunggu',
                'submitted_at' => now(),
            ]);
            $this->saveMembers($team, $user, $data, $request);
            ActivityLog::record('create_team', $team);

            return $team;
        });

        return $this->submitted($team, $user, $screening, 'Registrasi tim diajukan ke admin.');
    }

    /** Ketua mengubah registrasi yang belum diterima (menunggu/ditolak/draf) lalu mengajukannya kembali. */
    public function update(Request $request, ScreeningService $screening): RedirectResponse
    {
        $user = $request->user();
        $team = $this->team();
        if (! $team || $team->leader_id !== $user->id || ! in_array($team->status, ['draft', 'menunggu', 'ditolak'], true)) {
            throw ValidationException::withMessages(['team' => 'Registrasi tim tidak dapat diubah saat ini.']);
        }
        $this->ensureWindow('registrasi_tim', 'Registrasi tim');
        $data = $this->validateRegistration($request, $this->period(), $screening, $team);

        DB::transaction(function () use ($team, $user, $data, $request) {
            $team->update(['name' => $data['name'], 'status' => 'menunggu', 'submitted_at' => now()]);
            $this->saveMembers($team, $user, $data, $request);
        });

        return $this->submitted($team, $user, $screening, 'Perubahan registrasi tim diajukan ke admin.');
    }

    /**
     * Validasi formulir registrasi. Baris anggota tanpa mahasiswa terpilih diabaikan; KHS wajib
     * kecuali anggota tersebut sudah mengunggahnya pada pengajuan sebelumnya.
     */
    private function validateRegistration(Request $request, Period $period, ScreeningService $screening, ?Team $team): array
    {
        $request->merge(['members' => collect($request->input('members', []))->filter(fn ($m) => filled($m['user_id'] ?? null))->all()]);

        $min = config('capstone.team_size.min');
        $max = config('capstone.team_size.max');
        $existing = $team?->members->keyBy('user_id') ?? collect();
        $file = ['file', 'mimes:'.implode(',', config('capstone.upload.attachment_mimes')), 'max:'.config('capstone.upload.attachment_max_kb')];

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'leader_region' => ['required', 'string', 'max:100'],
            'leader_khs' => [$existing->get($request->user()->id)?->khs_path ? 'nullable' : 'required', ...$file],
            'members' => ['required', 'array', 'min:'.($min - 1), 'max:'.($max - 1)],
            'members.*.user_id' => ['required', 'integer', 'distinct'],
            'members.*.origin_region' => ['required', 'string', 'max:100'],
        ];
        foreach ($request->input('members', []) as $key => $row) {
            $rules["members.{$key}.khs"] = [$existing->get((int) $row['user_id'])?->khs_path ? 'nullable' : 'required', ...$file];
        }

        $data = $request->validate($rules, [
            'leader_region.required' => 'Isi daerah asal Anda.',
            'leader_khs.required' => 'Unggah KHS Anda.',
            'members.required' => 'Pilih anggota tim.',
            'members.min' => 'Tim beranggotakan '.$min.'–'.$max.' orang termasuk ketua.',
            'members.max' => 'Tim beranggotakan '.$min.'–'.$max.' orang termasuk ketua.',
            'members.*.user_id.distinct' => 'Mahasiswa yang sama dipilih lebih dari sekali.',
            'members.*.origin_region.required' => 'Isi daerah asal anggota.',
            'members.*.khs.required' => 'Unggah KHS anggota.',
        ]);

        // Anggota harus mahasiswa aktif, belum bertim di periode ini, dan memenuhi prasyarat (PRD 6.1).
        $students = User::query()->students()->where('is_active', true)
            ->whereIn('id', collect($data['members'])->pluck('user_id'))->get()->keyBy('id');
        $errors = [];
        foreach ($data['members'] as $key => $row) {
            $student = $students->get((int) $row['user_id']);
            $other = $student?->teamFor($period);
            $error = match (true) {
                ! $student => 'Mahasiswa tidak ditemukan.',
                $student->id === $request->user()->id => 'Anda sudah tercatat sebagai ketua.',
                $other && $other->id !== $team?->id => "{$student->name} sudah tergabung di tim lain.",
                default => $this->eligibilityError($student, $period, $screening),
            };
            if ($error) {
                $errors["members.{$key}.user_id"] = $error;
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /** Simpan ketua & anggota beserta daerah asal dan KHS; anggota yang tidak lagi dipilih dihapus. */
    private function saveMembers(Team $team, User $leader, array $data, Request $request): void
    {
        $rows = collect($data['members'])->map(fn ($row, $key) => [
            'user' => User::find($row['user_id']),
            'region' => $row['origin_region'],
            'khs' => $request->file("members.{$key}.khs"),
        ])->prepend(['user' => $leader, 'region' => $data['leader_region'], 'khs' => $request->file('leader_khs')]);

        $team->members()->whereNotIn('user_id', $rows->pluck('user.id'))->get()->each(function (TeamMember $member) {
            $this->deleteAttachments($member);
            $member->delete();
        });

        foreach ($rows as $row) {
            $user = $row['user'];
            $user->update(['origin_region' => trim($row['region'])]);
            $member = $team->members()->updateOrCreate(
                ['user_id' => $user->id],
                ['is_leader' => $user->id === $leader->id, 'invite_status' => 'diterima', 'responded_at' => now()],
            );
            if ($row['khs']) {
                if ($member->khs_path) {
                    Storage::disk('local')->delete($member->khs_path);
                }
                $member->update(['khs_path' => $row['khs']->store("registrations/{$team->id}/{$user->id}", 'local')]);
            }
            // Undangan tim lain yang masih tertunda tidak berlaku lagi.
            $user->memberships()->where('invite_status', 'diundang')->where('team_id', '!=', $team->id)
                ->update(['invite_status' => 'ditolak', 'responded_at' => now()]);
        }
    }

    private function submitted(Team $team, User $leader, ScreeningService $screening, string $message): RedirectResponse
    {
        $result = $screening->apply($team->fresh());
        ActivityLog::record('submit_team', $team, ['score' => $result['score']]);
        SystemNotice::sendTo(
            $team->activeUsers()->whereKeyNot($leader->id)->get(),
            'Anda didaftarkan ke tim '.$team->name,
            "{$leader->name} mendaftarkan tim dan menunggu validasi admin.",
            route('mahasiswa.team.show'),
        );

        return redirect()->route('mahasiswa.team.show')->with('status', $message.' Skor screening otomatis: '.$result['score'].'/100.');
    }

    /** Mahasiswa aktif yang dapat dipilih sebagai anggota: belum bertim di periode ini (kecuali tim sendiri). */
    private function availableStudents(User $user, Period $period, ?Team $team): Collection
    {
        return User::query()->students()->where('is_active', true)->whereKeyNot($user->id)
            ->whereDoesntHave('memberships', fn ($q) => $q->where('invite_status', 'diterima')
                ->whereHas('team', fn ($t) => $t->where('period_id', $period->id)->when($team, fn ($t) => $t->whereKeyNot($team->id))))
            ->orderBy('name')->get(['id', 'name', 'nim', 'origin_region']);
    }

    private function eligibilityError(User $student, Period $period, ScreeningService $screening): ?string
    {
        $rule = $period->registrationRule()->with('courses')->first();
        $failed = collect($screening->eligibility($student, $rule))->where('passed', false);

        return $failed->isEmpty() ? null : "{$student->name} belum memenuhi prasyarat: ".$failed->pluck('label')->implode('; ').'.';
    }

    /** PRD 6.1 — akses pembentukan kelompok ditunda bila prasyarat belum terpenuhi. */
    private function ensureEligible(User $user, ScreeningService $screening): void
    {
        $rule = $this->period()->registrationRule()->with('courses')->first();
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
