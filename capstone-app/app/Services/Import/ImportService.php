<?php

namespace App\Services\Import;

use App\Models\LecturerProfile;
use App\Models\Period;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\ScreeningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * F3 / F4 — impor data dosen, mahasiswa, dan tim.
 * Setiap impor melewati pratinjau: baris bermasalah diberi flag dan alasan,
 * hanya baris valid yang disimpan (Alur §6.2).
 */
class ImportService
{
    public const TEMPLATES = [
        'dosen' => ['nip', 'nama', 'kode_dosen', 'email', 'no_wa', 'peminatan'],
        'mahasiswa' => ['nim', 'nama', 'email', 'no_wa', 'sks_lulus', 'mk_lulus', 'daerah_asal', 'peminatan'],
        'tim' => ['nama_tim', 'nim_ketua', 'nim_anggota_2', 'nim_anggota_3'],
    ];

    public const REQUIRED = [
        'dosen' => ['nama', 'kode_dosen', 'email'],
        'mahasiswa' => ['nim', 'nama', 'email'],
        'tim' => ['nama_tim', 'nim_ketua', 'nim_anggota_2'],
    ];

    public function __construct(private readonly ScreeningService $screening) {}

    /**
     * @return list<array{line: int, data: array, errors: list<string>, action: string}>
     */
    public function preview(string $type, array $rows, ?Period $period = null): array
    {
        $seen = [];

        return array_map(function (array $row) use ($type, &$seen, $period) {
            $d = $row['data'];
            $errors = [];
            foreach ($this->requiredColumns($type, $d) as $col) {
                if (($d[$col] ?? '') === '') {
                    $errors[] = "Kolom {$col} wajib diisi.";
                }
            }

            $action = 'baru';
            match ($type) {
                'dosen' => $this->validateLecturer($d, $errors, $seen, $action),
                'mahasiswa' => $this->validateStudent($d, $errors, $seen, $action),
                'tim' => $this->validateTeam($d, $errors, $seen, $action, $period),
            };

            return ['line' => $row['line'], 'data' => $d, 'errors' => $errors, 'action' => $action];
        }, $rows);
    }

    /**
     * Kolom wajib per baris. Mahasiswa yang NIM-nya sudah terdaftar cukup diisi nim + kolom yang ingin diubah
     * (mis. nim + peminatan); nama & email hanya wajib untuk mahasiswa baru.
     */
    private function requiredColumns(string $type, array $d): array
    {
        if ($type === 'mahasiswa' && ($d['nim'] ?? '') !== '' && User::query()->students()->where('nim', $d['nim'])->exists()) {
            return ['nim'];
        }

        return self::REQUIRED[$type];
    }

    /** Menyimpan baris valid. @return int jumlah baris tersimpan */
    public function commit(string $type, array $preview, ?Period $period = null): int
    {
        $valid = array_filter($preview, fn ($r) => $r['errors'] === []);

        DB::transaction(function () use ($type, $valid, $period) {
            foreach ($valid as $row) {
                match ($type) {
                    'dosen' => $this->saveLecturer($row['data']),
                    'mahasiswa' => $this->saveStudent($row['data']),
                    'tim' => $this->saveTeam($row['data'], $period),
                };
            }
        });

        return count($valid);
    }

    private function validateLecturer(array $d, array &$errors, array &$seen, string &$action): void
    {
        $email = Str::lower($d['email'] ?? '');
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }
        foreach (['email' => $email, 'kode' => Str::upper($d['kode_dosen'] ?? '')] as $key => $value) {
            if ($value !== '' && isset($seen["{$key}:{$value}"])) {
                $errors[] = "Duplikat {$key} di dalam file.";
            }
            $seen["{$key}:{$value}"] = true;
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing && $existing->role !== 'dosen') {
            $errors[] = 'Email sudah terdaftar sebagai '.$existing->roleLabel().'.';
        }
        $codeOwner = LecturerProfile::query()->where('lecturer_code', Str::upper($d['kode_dosen'] ?? ''))->first();
        if ($codeOwner && (! $existing || $codeOwner->user_id !== $existing->id)) {
            $errors[] = 'Kode dosen sudah dipakai dosen lain.';
        }
        $action = $existing ? 'perbarui' : 'baru';
    }

    private function validateStudent(array $d, array &$errors, array &$seen, string &$action): void
    {
        $email = Str::lower($d['email'] ?? '');
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }
        if (($d['sks_lulus'] ?? '') !== '' && ! ctype_digit($d['sks_lulus'])) {
            $errors[] = 'sks_lulus harus berupa angka.';
        }
        foreach (['nim' => $d['nim'] ?? '', 'email' => $email] as $key => $value) {
            if ($value !== '' && isset($seen["{$key}:{$value}"])) {
                $errors[] = "Duplikat {$key} di dalam file.";
            }
            $seen["{$key}:{$value}"] = true;
        }

        $byEmail = $email !== '' ? User::query()->where('email', $email)->first() : null;
        $byNim = ($d['nim'] ?? '') !== '' ? User::query()->where('nim', $d['nim'])->first() : null;
        if ($byEmail && $byEmail->role !== 'mahasiswa') {
            $errors[] = 'Email sudah terdaftar sebagai '.$byEmail->roleLabel().'.';
        }
        if ($byEmail && $byNim && $byEmail->id !== $byNim->id) {
            $errors[] = 'NIM dan email milik dua akun berbeda.';
        }
        $action = ($byEmail || $byNim) ? 'perbarui' : 'baru';
    }

    private function validateTeam(array $d, array &$errors, array &$seen, string &$action, ?Period $period): void
    {
        if (! $period) {
            $errors[] = 'Belum ada periode aktif.';

            return;
        }
        $nims = array_values(array_filter([$d['nim_ketua'] ?? '', $d['nim_anggota_2'] ?? '', $d['nim_anggota_3'] ?? '']));
        if (count($nims) !== count(array_unique($nims))) {
            $errors[] = 'NIM anggota tidak boleh berulang.';
        }
        $name = mb_strtolower(trim($d['nama_tim'] ?? ''));
        if ($name !== '' && (Team::query()->where('period_id', $period->id)->whereRaw('lower(name) = ?', [$name])->exists() || isset($seen["name:{$name}"]))) {
            $errors[] = 'Nama tim sudah digunakan pada periode ini.';
        }
        $seen["name:{$name}"] = true;

        foreach ($nims as $nim) {
            $student = User::query()->students()->where('nim', $nim)->first();
            if (! $student) {
                $errors[] = "NIM {$nim} belum terdaftar.";

                continue;
            }
            $inTeam = TeamMember::query()->where('user_id', $student->id)->where('invite_status', 'diterima')
                ->whereHas('team', fn ($q) => $q->where('period_id', $period->id))->exists();
            if ($inTeam || isset($seen["nim:{$nim}"])) {
                $errors[] = "NIM {$nim} sudah tergabung di kelompok lain.";
            }
            $seen["nim:{$nim}"] = true;
        }
    }

    private function saveLecturer(array $d): void
    {
        $user = User::query()->updateOrCreate(['email' => Str::lower($d['email'])], [
            'name' => $d['nama'],
            'role' => 'dosen',
            'phone' => ($d['no_wa'] ?? '') ?: null,
        ]);
        // F1 — password awal akun baru = kode dosen; dapat diganti admin di menu Data dosen.
        if (! $user->password) {
            $user->forceFill(['password' => Str::upper($d['kode_dosen'])])->save();
        }

        LecturerProfile::query()->updateOrCreate(['user_id' => $user->id], [
            'lecturer_code' => Str::upper($d['kode_dosen']),
            'nip' => ($d['nip'] ?? '') ?: null,
            'specialization' => ($d['peminatan'] ?? '') ?: null,
        ]);
    }

    private function saveStudent(array $d): void
    {
        $email = Str::lower($d['email'] ?? '');
        $user = User::query()->where('nim', $d['nim'])->first()
            ?? ($email !== '' ? User::query()->where('email', $email)->first() : null)
            ?? new User(['role' => 'mahasiswa']);
        // Kolom kosong tidak menimpa data lama (impor nim + peminatan hanya mengubah peminatan).
        $user->fill(array_filter([
            'nim' => $d['nim'],
            'name' => ($d['nama'] ?? '') ?: null,
            'email' => $email ?: null,
            'phone' => ($d['no_wa'] ?? '') ?: null,
            'sks_passed' => ($d['sks_lulus'] ?? '') !== '' ? (int) $d['sks_lulus'] : null,
            'passed_courses' => ($d['mk_lulus'] ?? '') !== '' ? array_values(array_filter(array_map(fn ($c) => Str::upper(trim($c)), preg_split('/[;,|]/', $d['mk_lulus'])))) : null,
            'origin_region' => ($d['daerah_asal'] ?? '') ?: null,
            'specialization' => ($d['peminatan'] ?? '') ?: null,
        ], fn ($v) => $v !== null));
        $user->role = 'mahasiswa';
        // F1 — password awal akun baru = NIM; dapat diganti admin di menu Data mahasiswa.
        $user->password ??= $d['nim'];
        $user->save();
    }

    private function saveTeam(array $d, Period $period): void
    {
        $nims = array_values(array_filter([$d['nim_ketua'], $d['nim_anggota_2'] ?? '', $d['nim_anggota_3'] ?? '']));
        $students = User::query()->whereIn('nim', $nims)->get()->keyBy('nim');
        $leader = $students[$d['nim_ketua']];

        // Hasil impor masuk status "menunggu" validasi admin, sama seperti tim manual (Alur §6.2).
        $team = Team::create([
            'period_id' => $period->id,
            'name' => trim($d['nama_tim']),
            'leader_id' => $leader->id,
            'status' => 'menunggu',
            'submitted_at' => now(),
        ]);

        foreach ($nims as $nim) {
            $team->members()->create([
                'user_id' => $students[$nim]->id,
                'is_leader' => $nim === $d['nim_ketua'],
                'invite_status' => 'diterima',
                'responded_at' => now(),
            ]);
        }

        $this->screening->apply($team);
    }

}
