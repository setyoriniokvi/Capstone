<?php

namespace App\Services\Import;

use App\Models\LecturerProfile;
use App\Models\Period;
use App\Models\StudyProgram;
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
        'dosen' => ['nip', 'nama', 'kode_dosen', 'email', 'no_wa', 'prodi', 'peminatan', 'hak_akses'],
        'mahasiswa' => ['nim', 'nama', 'email', 'prodi', 'no_wa', 'sks_lulus', 'mk_lulus', 'daerah_asal', 'peminatan'],
        'tim' => ['group_code', 'nama_tim', 'nim_ketua', 'nim_anggota_2', 'nim_anggota_3', 'prodi'],
    ];

    public const REQUIRED = [
        'dosen' => ['nama', 'kode_dosen', 'email', 'hak_akses'],
        'mahasiswa' => ['nim', 'nama', 'email', 'prodi'],
        'tim' => ['group_code', 'nim_ketua', 'nim_anggota_2', 'prodi'],
    ];

    public const ACCESS = [
        'pengusul' => 'can_propose_topic',
        'pembimbing' => 'can_supervise',
        'penguji' => 'can_examine',
        'dosen_kelas' => 'is_class_lecturer',
    ];

    public function __construct(private readonly ScreeningService $screening) {}

    /**
     * @return list<array{line: int, data: array, errors: list<string>, action: string}>
     */
    public function preview(string $type, array $rows, ?Period $period = null): array
    {
        $prodis = StudyProgram::query()->pluck('code')->map(fn ($c) => strtoupper($c))->all();
        $seen = [];

        return array_map(function (array $row) use ($type, $prodis, &$seen, $period) {
            $d = $row['data'];
            $errors = [];
            foreach (self::REQUIRED[$type] as $col) {
                if (($d[$col] ?? '') === '') {
                    $errors[] = "Kolom {$col} wajib diisi.";
                }
            }

            if (isset($d['prodi']) && $d['prodi'] !== '' && ! in_array(strtoupper($d['prodi']), $prodis, true)) {
                $errors[] = "Prodi {$d['prodi']} tidak dikenal.";
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

        $unknown = array_diff($this->accessList($d['hak_akses'] ?? ''), array_keys(self::ACCESS));
        if ($unknown) {
            $errors[] = 'Hak akses tidak dikenal: '.implode(', ', $unknown).'. Gunakan: '.implode(', ', array_keys(self::ACCESS)).'.';
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

        $byEmail = User::query()->where('email', $email)->first();
        $byNim = User::query()->where('nim', $d['nim'] ?? '')->first();
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
        if (Team::query()->where('code', $d['group_code'] ?? '')->exists() || isset($seen['code:'.($d['group_code'] ?? '')])) {
            $errors[] = 'Kode kelompok sudah digunakan.';
        }
        $seen['code:'.($d['group_code'] ?? '')] = true;

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
        $access = $this->accessList($d['hak_akses']);
        $user = User::query()->updateOrCreate(['email' => Str::lower($d['email'])], [
            'name' => $d['nama'],
            'role' => 'dosen',
            'prodi_code' => ($d['prodi'] ?? '') !== '' ? Str::upper($d['prodi']) : null,
            'phone' => ($d['no_wa'] ?? '') ?: null,
        ]);

        LecturerProfile::query()->updateOrCreate(['user_id' => $user->id], [
            'lecturer_code' => Str::upper($d['kode_dosen']),
            'nip' => ($d['nip'] ?? '') ?: null,
            'specialization' => ($d['peminatan'] ?? '') ?: null,
        ] + collect(self::ACCESS)->mapWithKeys(fn ($column, $name) => [$column => in_array($name, $access, true)])->all());
    }

    private function saveStudent(array $d): void
    {
        $user = User::query()->where('nim', $d['nim'])->orWhere('email', Str::lower($d['email']))->first() ?? new User(['role' => 'mahasiswa']);
        $user->fill(array_filter([
            'nim' => $d['nim'],
            'name' => $d['nama'],
            'email' => Str::lower($d['email']),
            'prodi_code' => Str::upper($d['prodi']),
            'phone' => ($d['no_wa'] ?? '') ?: null,
            'sks_passed' => ($d['sks_lulus'] ?? '') !== '' ? (int) $d['sks_lulus'] : null,
            'passed_courses' => ($d['mk_lulus'] ?? '') !== '' ? array_values(array_filter(array_map(fn ($c) => Str::upper(trim($c)), preg_split('/[;,|]/', $d['mk_lulus'])))) : null,
            'origin_region' => ($d['daerah_asal'] ?? '') ?: null,
            'specialization' => ($d['peminatan'] ?? '') ?: null,
        ], fn ($v) => $v !== null));
        $user->role = 'mahasiswa';
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
            'code' => $d['group_code'],
            'name' => ($d['nama_tim'] ?? '') ?: $d['group_code'],
            'prodi_code' => Str::upper($d['prodi']),
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

    private function accessList(string $value): array
    {
        $map = ['keduanya' => ['pembimbing', 'penguji']];

        return collect(preg_split('/[;,|]/', Str::lower($value)))
            ->map(fn ($v) => str_replace([' ', '-'], '_', trim($v)))
            ->filter()
            ->flatMap(fn ($v) => $map[$v] ?? [$v])
            ->unique()->values()->all();
    }
}
