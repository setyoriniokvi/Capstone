<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\RegistrationRule;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\AccountActivation;
use App\Notifications\SystemNotice;
use App\Notifications\TeamRegistrationDecision;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * F21 — registrasi akun + tim dalam satu formulir. Ketua mengisi data dirinya dan seluruh anggota
 * (NIM, nama, email, peminatan opsional, SKS lulus, indeks nilai mata kuliah prasyarat, daerah asal, KHS).
 * Akun anggota dibuat dari isian ketua tanpa password; setelah admin menerima tim, anggota menerima
 * email keputusan berisi tautan atur password (notifyDecision). Mahasiswa yang sudah ada dicocokkan lewat NIM/email.
 *
 * Dipakai halaman registrasi publik (ketua membuat akun) dan tombol Registrasi Tim di menu Tim Saya (mahasiswa yang sudah
 * punya akun tetapi belum bertim, mis. timnya terhapus admin). Tim yang sudah terdaftar hanya diubah admin.
 */
class TeamRegistration
{
    public function __construct(private ScreeningService $screening) {}

    /**
     * Mahasiswa yang dapat di-invite ketua dari menu Tim Saya: aktif, belum bertim pada periode ini, bukan ketua sendiri.
     * Akun tetap tersimpan walau timnya dihapus admin, jadi anggota lama dapat dipilih lagi.
     */
    public function availableStudents(Period $period, User $leader): Collection
    {
        return User::query()->students()->where('is_active', true)->whereKeyNot($leader->id)
            ->whereDoesntHave('memberships', fn ($q) => $q->where('invite_status', 'diterima')
                ->whereHas('team', fn ($t) => $t->where('period_id', $period->id)))
            ->orderBy('name')->get(['id', 'name', 'nim', 'email', 'specialization', 'origin_region']);
    }

    /**
     * Baris anggota pada registrasi dari Tim Saya: mahasiswa dipilih (invite) dari daftar, bukan diketik. Identitas
     * (NIM, nama, email) diambil dari akun terpilih; baris tanpa pilihan diabaikan.
     */
    private function invitedMembers(Request $request, Period $period, User $leader): Collection
    {
        $available = $this->availableStudents($period, $leader)->keyBy('id');
        $members = collect($request->input('members', []))->filter(fn ($m) => is_array($m) && filled($m['user_id'] ?? null));
        $request->merge(['members' => $members->all()]);
        $request->validate([
            'members.*.user_id' => ['required', 'integer', 'distinct', Rule::in($available->keys()->all())],
        ], [
            'members.*.user_id.distinct' => 'Mahasiswa yang sama dipilih lebih dari sekali.',
            'members.*.user_id.in' => 'Mahasiswa sudah tergabung di tim lain pada periode ini atau tidak aktif.',
        ]);

        return $members->map(function (array $m) use ($available) {
            $user = $available->get((int) $m['user_id']);

            return ['nim' => $user->nim, 'name' => $user->name, 'email' => $user->email] + $m;
        });
    }

    /** Pilihan formulir: mata kuliah prasyarat & peminatan dari aturan periode, indeks nilai dari config. */
    public function options(?Period $period): array
    {
        $rule = $period?->registrationRule()->with('courses', 'specializations')->first();

        return [
            'courses' => $rule?->courses ?? collect(),
            'specializations' => $rule?->specializations->pluck('name') ?? collect(),
            'grades' => config('capstone.course_grades'),
            'teamSize' => config('capstone.team_size'),
        ];
    }

    /** Alasan registrasi tidak dapat diajukan saat ini (tidak ada periode aktif / di luar milestone registrasi). */
    public function windowError(?Period $period): ?string
    {
        if (! $period) {
            return 'Belum ada periode Capstone yang aktif.';
        }
        $milestones = $period->milestones()->where('kind', 'registrasi_tim')->orderBy('starts_at')->get();
        if ($milestones->isEmpty() || $milestones->contains(fn ($m) => $m->isOpen())) {
            return null;
        }

        return now()->lt($milestones->first()->starts_at)
            ? 'Registrasi tim dibuka mulai '.$milestones->first()->starts_at->translatedFormat('j F Y H:i').'.'
            : 'Registrasi tim sudah ditutup pada '.$milestones->last()->due_at->translatedFormat('j F Y H:i').'.';
    }

    /**
     * Validasi formulir. $leader = mahasiswa yang sudah masuk dan mendaftar dari menu Tim Saya (mis. timnya terhapus admin):
     * identitas ketua diambil dari akunnya. Null = registrasi publik, ketua membuat akun + password.
     * Baris anggota yang NIM, nama, dan email-nya kosong diabaikan.
     *
     * @return array{name: string, people: Collection<int, array{path: string, user: ?User, data: array, leader: bool}>}
     */
    public function validate(Request $request, Period $period, ?User $leader = null): array
    {
        $members = $leader ? $this->invitedMembers($request, $period, $leader) : collect($request->input('members', []))
            ->filter(fn ($m) => is_array($m) && collect(Arr::only($m, ['nim', 'name', 'email']))->filter(fn ($v) => filled($v))->isNotEmpty())
            ->map(fn ($m) => ['email' => Str::lower(trim((string) ($m['email'] ?? '')))] + $m);
        $leaderInput = (array) $request->input('leader', []);
        $leaderInput = $leader
            ? ['nim' => $leader->nim, 'name' => $leader->name, 'email' => $leader->email] + $leaderInput
            : ['email' => Str::lower(trim((string) ($leaderInput['email'] ?? '')))] + $leaderInput;
        $request->merge(['leader' => $leaderInput, 'members' => $members->all()]);

        $options = $this->options($period);
        ['min' => $min, 'max' => $max] = $options['teamSize'];
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'members' => ['required', 'array', 'min:'.($min - 1), 'max:'.($max - 1)],
        ] + $this->personRules('leader', $options);
        if (! $leader) {
            $rules['leader.password'] = ['required', 'string', 'min:8', 'max:100', 'confirmed'];
        }
        foreach ($members->keys() as $key) {
            $rules += $this->personRules("members.{$key}", $options);
        }

        $data = $request->validate($rules, $this->messages($min, $max));

        $people = collect(['leader' => $data['leader']])
            ->merge(collect($data['members'])->mapWithKeys(fn ($row, $key) => ["members.{$key}" => $row]))
            ->map(fn ($row, $path) => ['path' => $path, 'data' => $row, 'leader' => $path === 'leader', 'user' => null]);

        $errors = $this->duplicateErrors($people);
        if (Team::query()->where('period_id', $period->id)->where('name', $data['name'])->where('status', '!=', 'ditolak')->exists()) {
            $errors['name'] = 'Nama tim sudah dipakai pada periode ini.';
        }
        $rule = $period->registrationRule()->with('courses')->first();
        $people = $people->map(function (array $person) use ($period, $leader, $rule, $options, &$errors) {
            $person['user'] = $person['leader'] && $leader ? $leader : $this->resolveUser($person, $period, $leader, $errors);
            $errors += $this->eligibilityErrors($person, $rule, $options['courses']);

            return $person;
        });
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return ['name' => $data['name'], 'people' => $people->values()];
    }

    /** Simpan akun ketua & anggota, tim baru (status menunggu), dan lampiran KHS. */
    public function save(Period $period, array $data, Request $request): Team
    {
        $courses = $this->options($period)['courses'];

        return DB::transaction(function () use ($period, $data, $request, $courses) {
            $users = $data['people']->map(fn ($person) => $person + ['saved' => $this->saveUser($person, $courses)]);
            $leader = $users->firstWhere('leader', true)['saved'];

            $team = Team::create([
                'period_id' => $period->id,
                'name' => $data['name'],
                'leader_id' => $leader->id,
                'status' => 'menunggu',
                'submitted_at' => now(),
            ]);

            foreach ($users as $person) {
                $user = $person['saved'];
                $team->members()->create([
                    'user_id' => $user->id,
                    'is_leader' => $person['leader'],
                    // Akun yang dibuat dari isian registrasi ini — dihapus lagi bila tim ditolak (dissolve).
                    'account_created' => $person['user'] === null,
                    'invite_status' => 'diterima',
                    'responded_at' => now(),
                    'khs_path' => $request->file($person['path'].'.khs')->store("registrations/{$team->id}/{$user->id}", 'local'),
                ]);
                // Undangan tim lain yang masih tertunda tidak berlaku lagi.
                $user->memberships()->where('invite_status', 'diundang')->where('team_id', '!=', $team->id)
                    ->update(['invite_status' => 'ditolak', 'responded_at' => now()]);
            }

            return $team;
        });
    }

    /** Jalankan screening, catat log, dan beri tahu anggota (notifikasi terbaca setelah akun anggota aktif). */
    public function submitted(Team $team, User $leader): array
    {
        $result = $this->screening->apply($team->fresh());
        ActivityLog::record('create_team', $team, ['score' => $result['score']]);
        SystemNotice::sendTo(
            $team->activeUsers()->whereKeyNot($leader->id)->get(),
            'Anda didaftarkan ke tim '.$team->name,
            "{$leader->name} mendaftarkan tim dan menunggu validasi admin.",
            route('mahasiswa.team.show'),
        );

        return $result;
    }

    /**
     * Tim ditolak: data pendaftar disimpan sebagai log (teams.archived_members), lalu keanggotaan & KHS dihapus
     * dan akun yang dibuat lewat registrasi ikut dihapus — mahasiswa kembali seperti belum mendaftar.
     * Akun yang sudah ada sebelumnya (mis. impor admin) hanya dilepas dari tim.
     */
    public function dissolve(Team $team): void
    {
        $team->loadMissing('members.user', 'period.registrationRule.courses');
        $courses = $team->period->registrationRule?->courses ?? collect();

        DB::transaction(function () use ($team, $courses) {
            $team->forceFill(['archived_members' => $team->members->map(fn (TeamMember $m) => [
                'name' => $m->user->name, 'nim' => $m->user->nim, 'email' => $m->user->email, 'is_leader' => $m->is_leader,
                'specialization' => $m->user->specialization, 'sks_passed' => $m->user->sks_passed, 'origin_region' => $m->user->origin_region,
                'grades' => $courses->mapWithKeys(fn ($c) => [$c->code => ($m->user->course_grades ?? [])[$c->code] ?? null])->filter()->all(),
            ])->values()->all(), 'leader_id' => null])->save();

            foreach ($team->members as $member) {
                Storage::disk('local')->delete(array_filter([$member->khs_path, $member->domicile_path]));
                $member->delete();
                if ($member->account_created && ! $member->user->memberships()->exists()) {
                    $member->user->notifications()->delete();
                    $member->user->delete();
                }
            }
            Storage::disk('local')->deleteDirectory("registrations/{$team->id}");
        });
    }

    /**
     * Email keputusan registrasi ke setiap anggota (tombol "Atur password" untuk akun yang dibuat ketua).
     * Gagal kirim (mis. SMTP belum diatur) tidak membatalkan keputusan; dikembalikan jumlah email terkirim & gagal.
     *
     * @return array{sent: int, failed: int}
     */
    public function notifyDecision(Team $team, bool $approved, ?string $notes): array
    {
        return $this->mail($team->activeUsers()->get(), fn () => new TeamRegistrationDecision($team, $approved, $notes));
    }

    /** Kirim ulang tautan atur password ke anggota tim diterima yang belum mengatur password. */
    public function resendActivation(Team $team): array
    {
        $pending = $team->activeUsers()->get()->filter(fn (User $u) => $u->awaitingActivation());

        return $this->mail($pending, fn () => new AccountActivation($team));
    }

    private function mail(Collection $users, \Closure $notification): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        foreach ($users as $user) {
            try {
                $user->notify($notification());
                $result['sent']++;
            } catch (\Throwable $e) {
                report($e);
                $result['failed']++;
            }
        }

        return $result;
    }

    private function personRules(string $path, array $options): array
    {
        $rules = [
            "{$path}.nim" => ['required', 'string', 'regex:/^[0-9]{6,20}$/'],
            "{$path}.name" => ['required', 'string', 'max:150'],
            "{$path}.email" => ['required', 'email', 'max:150'],
            "{$path}.specialization" => ['nullable', 'string', Rule::in($options['specializations']->all())],
            "{$path}.sks_passed" => ['required', 'integer', 'between:0,200'],
            "{$path}.origin_region" => ['required', 'string', 'max:100'],
            "{$path}.khs" => ['required', 'file',
                'mimes:'.implode(',', config('capstone.upload.attachment_mimes')), 'max:'.config('capstone.upload.attachment_max_kb')],
        ];
        foreach ($options['courses'] as $course) {
            $rules["{$path}.grades.{$course->id}"] = ['required', Rule::in($options['grades'])];
        }

        return $rules;
    }

    private function messages(int $min, int $max): array
    {
        // Pola * juga mencocokkan "members.N.…".
        return [
            'members.required' => 'Isi data anggota tim.',
            'members.min' => 'Tim beranggotakan '.$min.'–'.$max.' orang termasuk ketua.',
            'members.max' => 'Tim beranggotakan '.$min.'–'.$max.' orang termasuk ketua.',
            '*.nim.regex' => 'NIM hanya berisi angka.',
            '*.specialization.in' => 'Pilih peminatan dari daftar.',
            '*.khs.required' => 'Unggah KHS.',
            '*.grades.*.required' => 'Pilih indeks nilai.',
            'leader.password.confirmed' => 'Konfirmasi password tidak sama.',
        ];
    }

    /** NIM / email yang sama diisi lebih dari sekali dalam formulir. */
    private function duplicateErrors(Collection $people): array
    {
        $errors = [];
        foreach (['nim' => 'NIM', 'email' => 'Email'] as $field => $label) {
            $seen = [];
            foreach ($people as $person) {
                $value = (string) $person['data'][$field];
                if (isset($seen[$value])) {
                    $errors["{$person['path']}.{$field}"] = "{$label} yang sama diisi lebih dari sekali.";
                }
                $seen[$value] = true;
            }
        }

        return $errors;
    }

    /**
     * Cocokkan isian dengan akun yang sudah ada (NIM atau email). Akun lama dipakai bila NIM & email-nya sama,
     * mahasiswa aktif, dan belum bertim; ketua registrasi publik tidak boleh mengambil akun yang sudah bisa masuk.
     */
    private function resolveUser(array $person, Period $period, ?User $leader, array &$errors): ?User
    {
        ['path' => $path, 'data' => $d] = $person;
        $byNim = User::query()->where('nim', $d['nim'])->first();
        $byEmail = User::query()->where('email', $d['email'])->first();
        $user = $byNim ?? $byEmail;
        if (! $user) {
            return null;
        }

        $error = match (true) {
            ! $user->isStudent() => ["{$path}.email", 'Email sudah dipakai akun non-mahasiswa.'],
            $byNim && $byNim->email !== $d['email'] => ["{$path}.nim", "NIM {$d['nim']} sudah terdaftar dengan email lain."],
            $byEmail && filled($byEmail->nim) && $byEmail->nim !== $d['nim'] => ["{$path}.email", 'Email ini sudah terdaftar dengan NIM lain.'],
            ! $user->is_active => ["{$path}.nim", "Akun {$user->name} dinonaktifkan admin."],
            $person['leader'] && ! $user->awaitingActivation() => ['leader.nim', 'NIM ini sudah memiliki akun. Masuk dengan akun Anda, lalu registrasi tim dari menu Tim Saya.'],
            $leader && $user->is($leader) => ["{$path}.nim", 'Anda sudah tercatat sebagai ketua.'],
            (bool) $user->teamFor($period) => ["{$path}.nim", "{$user->name} sudah tergabung di tim lain."],
            default => null,
        };
        if ($error) {
            $errors[$error[0]] ??= $error[1];
        }

        return $user;
    }

    /** PRD 6.1 — ketua & anggota harus memenuhi SKS minimal dan lulus mata kuliah prasyarat. */
    private function eligibilityErrors(array $person, ?RegistrationRule $rule, Collection $courses): array
    {
        $probe = new User(['sks_passed' => (int) $person['data']['sks_passed'], 'passed_courses' => $person['user']?->passed_courses]);
        $probe->setCourseGrades($this->gradesByCode($person['data'], $courses));
        $failed = collect($this->screening->eligibility($probe, $rule))->where('passed', false);

        $errors = [];
        foreach ($failed as $check) {
            $course = $courses->first(fn ($c) => $check['key'] === 'mk_'.$c->code);
            $field = $course ? "{$person['path']}.grades.{$course->id}" : "{$person['path']}.sks_passed";
            $errors[$field] = 'Belum memenuhi prasyarat: '.$check['label'].'.';
        }

        return $errors;
    }

    private function saveUser(array $person, Collection $courses): User
    {
        $d = $person['data'];
        $user = $person['user'] ?? new User(['is_active' => true]);
        $user->fill([
            'nim' => $d['nim'],
            'name' => trim($d['name']),
            'email' => $d['email'],
            'sks_passed' => (int) $d['sks_passed'],
            'origin_region' => trim($d['origin_region']),
        ]);
        // Peminatan kosong tidak menimpa peminatan yang sudah diisi admin untuk NIM ini.
        if (filled($d['specialization'] ?? null)) {
            $user->specialization = $d['specialization'];
        }
        if (filled($d['password'] ?? null)) {
            $user->password = $d['password'];
        }
        $user->role = 'mahasiswa';
        $user->setCourseGrades($this->gradesByCode($d, $courses))->save();

        return $user;
    }

    private function gradesByCode(array $d, Collection $courses): array
    {
        return $courses->mapWithKeys(fn ($c) => [$c->code => $d['grades'][$c->id] ?? null])->filter()->all();
    }
}
