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
        'dosen' => ['nip', 'nama', 'kode_dosen', 'email', 'no_wa', 'peminatan', 'hak_akses'],
        'mahasiswa' => ['nim', 'nama', 'email', 'kelas', 'no_wa', 'sks_lulus', 'mk_lulus', 'daerah_asal', 'peminatan'],
        'tim' => ['nama_tim', 'nim_ketua', 'nim_anggota_2', 'nim_anggota_3'],
    ];

    public const REQUIRED = [
        'dosen' => ['nama', 'kode_dosen', 'email'],
        'mahasiswa' => ['nim', 'nama', 'email'],
        'tim' => ['nama_tim', 'nim_ketua', 'nim_anggota_2'],
    ];

    /**
     * Kolom yang wajib ada di header file. Dosen cukup kode_dosen: baris tanpa email dicocokkan dengan dosen periode
     * ini menurut kode (mis. file berisi kode_dosen + hak_akses saja untuk menandai Dosen Kelas). Mahasiswa cukup nim:
     * mahasiswa terdaftar diperbarui menurut NIM (mis. nim + kelas); nama & email tetap wajib per baris untuk mahasiswa baru.
     */
    public static function headerColumns(string $type): array
    {
        return match ($type) {
            'dosen' => ['kode_dosen'],
            'mahasiswa' => ['nim'],
            default => self::REQUIRED[$type],
        };
    }

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
        // Dosen periode ini dicocokkan menurut kode: cukup kode_dosen + kolom yang ingin diubah (mis. hak_akses).
        if ($type === 'dosen' && ($d['email'] ?? '') === '' && $this->lecturerByCode($d)) {
            return ['kode_dosen'];
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
                    'dosen' => $this->saveLecturer($row['data'], $period),
                    'mahasiswa' => $this->saveStudent($row['data'], $period),
                    'tim' => $this->saveTeam($row['data'], $period),
                };
            }
        });

        return count($valid);
    }

    /** Profil dosen periode yang sedang dibuka dengan kode_dosen baris ini. */
    private function lecturerByCode(array $d): ?LecturerProfile
    {
        $code = Str::upper(trim($d['kode_dosen'] ?? ''));

        return $code === '' ? null : LecturerProfile::query()->where('lecturer_code', $code)->first();
    }

    /**
     * Kolom hak_akses: kata kunci dipisah titik koma / koma (dosen_kelas, pembimbing, penguji; "Dosen Kelas" juga
     * diterima). Hak akses ditambahkan, tidak menghapus hak akses yang sudah ada.
     *
     * @return array{columns: list<string>, unknown: list<string>}
     */
    private function accessColumns(array $d): array
    {
        $columns = [];
        $unknown = [];
        foreach (preg_split('/[;,|]/', (string) ($d['hak_akses'] ?? '')) as $token) {
            $key = Str::of($token)->trim()->lower()->replaceMatches('/[\s\-]+/', '_')->toString();
            if ($key === '' || $key === 'dosen') {
                continue;
            }
            isset(LecturerProfile::IMPORT_ACCESS[$key]) ? $columns[] = LecturerProfile::IMPORT_ACCESS[$key] : $unknown[] = trim($token);
        }

        return ['columns' => array_values(array_unique($columns)), 'unknown' => $unknown];
    }

    private function validateLecturer(array $d, array &$errors, array &$seen, string &$action): void
    {
        if ($unknown = $this->accessColumns($d)['unknown']) {
            $errors[] = 'Hak akses tidak dikenal: '.implode(', ', $unknown).'. Gunakan dosen_kelas, pembimbing, atau penguji.';
        }
        if (($d['email'] ?? '') === '') {
            if (isset($seen['kode:'.Str::upper($d['kode_dosen'] ?? '')])) {
                $errors[] = 'Duplikat kode di dalam file.';
            }
            $seen['kode:'.Str::upper($d['kode_dosen'] ?? '')] = true;
            if (($d['kode_dosen'] ?? '') !== '' && ! $this->lecturerByCode($d)) {
                $errors[] = 'Kode dosen tidak ditemukan di periode ini. Isi nama & email untuk menambah dosen baru.';
            }
            $action = 'perbarui';

            return;
        }

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
        if (mb_strlen(trim($d['kelas'] ?? '')) > 30) {
            $errors[] = 'kelas maksimal 30 karakter.';
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

    /** Akun dosen/mahasiswa hasil impor terdaftar di periode yang sedang dibuka. */
    private function saveLecturer(array $d, ?Period $period): void
    {
        $access = array_fill_keys($this->accessColumns($d)['columns'], true);

        // Tanpa email: dosen periode ini dicocokkan menurut kode; kolom kosong tidak menimpa data, hak akses ditambahkan.
        if (($d['email'] ?? '') === '') {
            $this->lecturerByCode($d)?->update(array_filter([
                'nip' => ($d['nip'] ?? '') ?: null,
                'specialization' => ($d['peminatan'] ?? '') ?: null,
                'phone' => ($d['no_wa'] ?? '') ?: null,
            ], fn ($v) => $v !== null) + $access);

            return;
        }

        $user = User::query()->updateOrCreate(['email' => Str::lower($d['email'])], [
            'name' => $d['nama'],
            'role' => 'dosen',
        ]);
        if (! $user->phone && ($d['no_wa'] ?? '') !== '') {
            $user->forceFill(['phone' => $d['no_wa']])->save();
        }
        // F1 — password awal akun baru = password default dosen (12345678); dosen menggantinya lewat menu Ganti Password.
        if (! $user->password) {
            $user->forceFill(['password' => config('capstone.lecturer_default_password')])->save();
        }

        LecturerProfile::query()->updateOrCreate(['user_id' => $user->id], [
            'lecturer_code' => Str::upper($d['kode_dosen']),
            'nip' => ($d['nip'] ?? '') ?: null,
            'specialization' => ($d['peminatan'] ?? '') ?: null,
            'phone' => ($d['no_wa'] ?? '') ?: null,
        ] + $access);
        $user->joinPeriod($period);
    }

    private function saveStudent(array $d, ?Period $period): void
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
            'class_name' => ($d['kelas'] ?? '') !== '' ? Str::upper(trim($d['kelas'])) : null,
        ], fn ($v) => $v !== null));
        $user->role = 'mahasiswa';
        // F1 — password awal akun baru = NIM; dapat diganti admin di menu Data mahasiswa.
        $user->password ??= $d['nim'];
        $user->save();
        $user->joinPeriod($period);
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
