<?php

namespace App\Services;

use App\Models\AssessmentComponent;
use App\Models\DocumentVersion;
use App\Models\Milestone;
use App\Models\Period;
use App\Models\RegistrationRule;
use App\Models\TeamMember;
use App\Models\User;
use App\Support\PeriodContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * F2 / F5 / F35 — membuat & memperbarui periode beserta pengaturannya: aturan registrasi tim, folder Google Drive per
 * milestone dokumen, akun admin prodi, dan (khusus periode baru) milestone, jadwal penilaian & bentuk penilaian draf.
 * Isian sudah divalidasi formatnya oleh PeriodController; aturan yang butuh data (folder Drive, jadwal) dicek di sini.
 */
class PeriodSetup
{
    /**
     * @param  array  $data  isian periode (nama, tahun, semester, prodi, tanggal, minimal log bimbingan)
     * @param  ?int  $masterSource  periode sumber data master (null = data master bawaan)
     * @param  array<string, array>  $milestones  milestone draf: kunci draf (d1, d2, …) => isian milestone
     * @param  array<string, ?string>  $draftFolders  folder Drive per kunci milestone draf
     * @param  array<string, array{opens_at?: ?string, due_at?: ?string}>  $windows  jadwal bentuk penilaian hasil salinan
     * @param  list<array>  $components  bentuk penilaian dosen draf (LecturerComponentInput)
     * @param  array  $rule  aturan registrasi tim (PeriodController::validatedRule)
     * @param  array{email: ?string, password: ?string}  $admin  akun admin prodi
     */
    public function create(array $data, ?int $masterSource, array $milestones, array $draftFolders, array $windows, array $components, array $rule, array $admin): Period
    {
        $period = DB::transaction(function () use ($data, $masterSource, $milestones, $draftFolders, $windows, $components, $rule, $admin) {
            $period = new Period($data);
            // Data master (bentuk penilaian, CLO, indikator, rubrik, reminder, instruksi & kata kunci topik, dosen)
            // disalin dari periode yang dipilih, atau dari data master bawaan (PeriodObserver).
            $period->masterSource = $masterSource;
            $period->save();

            // Milestone draf → milestone periode; folder Drive draf dipetakan ke id milestone baru.
            $folders = [];
            $draftKeyOf = [];
            foreach ($milestones as $key => $milestone) {
                $id = $period->milestones()->create($milestone)->id;
                $draftKeyOf[$id] = $key;
                if (array_key_exists($key, $draftFolders)) {
                    $folders[$id] = $draftFolders[$key];
                }
            }

            try {
                PeriodContext::using($period, function () use ($period, $folders, $windows, $components, $rule) {
                    $this->syncRule($period, $rule);
                    $this->syncDriveFolders($period, $folders);
                    $this->createAssessmentSchedule($period, $windows, $components);
                });
            } catch (ValidationException $e) {
                // Kunci error folder dikembalikan ke kunci draf agar tampil di baris milestone draf halaman Periode baru.
                throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $field) => [
                    preg_replace_callback('/^drive_folders\.(\d+)$/', fn ($m) => 'drive_folders.'.($draftKeyOf[$m[1]] ?? $m[1]), $field) => $messages,
                ])->all());
            }
            $this->syncAdmin($period, $admin);

            return $period;
        });
        AssessmentComponent::syncConfig();

        return $period;
    }

    /**
     * @param  array<int|string, ?string>  $folders  folder Drive per id milestone dokumen (kunci tidak dikirim = tidak diubah)
     * @param  array{email: ?string, password: ?string}  $admin
     */
    public function update(Period $period, array $data, array $rule, array $folders, array $admin): void
    {
        DB::transaction(function () use ($period, $data, $rule, $folders, $admin) {
            $period->update($data);
            $this->syncRule($period, $rule);
            $this->syncDriveFolders($period, $folders);
            $this->syncAdmin($period, $admin);
        });
    }

    /**
     * Hapus periode beserta seluruh isinya (milestone, aturan, tim, topik, dokumen, log bimbingan, nilai — lewat cascade).
     * Berkas lokal (dokumen, KHS, domisili) ikut dihapus; berkas di Google Drive tidak.
     *
     * @return int jumlah tim yang ikut terhapus
     */
    public function delete(Period $period): int
    {
        $teamIds = $period->teams()->pluck('id');
        $files = DocumentVersion::query()->whereHas('document', fn ($d) => $d->whereIn('team_id', $teamIds))->pluck('local_path')
            ->merge(TeamMember::query()->whereIn('team_id', $teamIds)->pluck('khs_path'))
            ->merge(TeamMember::query()->whereIn('team_id', $teamIds)->pluck('domicile_path'))
            ->filter()->values();

        DB::transaction(fn () => $period->delete());
        Storage::disk(DocumentVersion::DISK)->delete($files->all());

        return $teamIds->count();
    }

    /**
     * F5 — satu aturan registrasi per periode: minimal SKS, jumlah anggota, syarat multikultural/multidisiplin,
     * mata kuliah prasyarat (satu per baris "KODE - Nama"), peminatan, dan kelas (satu per baris).
     */
    private function syncRule(Period $period, array $data): void
    {
        $lines = fn (?string $text) => collect(preg_split('/\r\n|\r|\n/', (string) $text))->map(fn ($l) => trim($l))->filter()->values();

        $rule = RegistrationRule::query()->where('period_id', $period->id)->orderBy('id')->first() ?? new RegistrationRule;
        $rule->fill([
            'classes' => $lines($data['classes'] ?? null)->map(fn ($c) => Str::upper($c))->unique()->values()->all(),
            'min_sks' => $data['min_sks'],
            'min_members' => $data['min_members'],
            'max_members' => $data['max_members'],
            'require_distinct_region' => $data['require_distinct_region'],
            'require_distinct_specialization' => $data['require_distinct_specialization'],
        ]);
        $period->registrationRules()->save($rule);

        $courses = $lines($data['courses'] ?? null)->map(function (string $line) {
            [$code, $name] = array_pad(preg_split('/\s+-\s+|\s*\|\s*/', $line, 2), 2, null);

            return ['code' => Str::upper(trim($code)), 'name' => trim($name ?? $code)];
        })->unique('code');
        $rule->courses()->whereNotIn('code', $courses->pluck('code'))->delete();
        foreach ($courses as $course) {
            $rule->courses()->updateOrCreate(['code' => $course['code']], ['name' => $course['name']]);
        }

        $specializations = $lines($data['specializations'] ?? null)->unique();
        $rule->specializations()->whereNotIn('name', $specializations)->delete();
        foreach ($specializations as $name) {
            $rule->specializations()->firstOrCreate(['name' => $name]);
        }
    }

    /**
     * F35 — satu folder Google Drive Database per milestone dokumen. Boleh diisi tautan folder atau ID-nya; kosong =
     * dokumen milestone tersebut belum disinkronkan ke Drive.
     *
     * @param  array<int|string, ?string>  $input  id milestone => tautan / ID folder
     */
    private function syncDriveFolders(Period $period, array $input): void
    {
        $milestones = $period->milestones()->where('kind', 'dokumen')->get();
        $errors = [];
        $folders = [];

        foreach ($milestones as $milestone) {
            $value = trim((string) ($input[$milestone->id] ?? ''));
            $folders[$milestone->id] = $value === '' ? null : Milestone::parseDriveFolder($value);
            if ($value !== '' && $folders[$milestone->id] === null) {
                $errors["drive_folders.{$milestone->id}"] = 'Tautan folder Google Drive tidak dikenali. Salin tautan folder dari address bar Google Drive.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($milestones as $milestone) {
            if (array_key_exists($milestone->id, $input)) {
                $milestone->update(['drive_folder_id' => $folders[$milestone->id]]);
            }
        }
    }

    /**
     * Periode baru: jadwal pengisian nilai untuk bentuk penilaian hasil salinan data master ($windows[key]) dan bentuk
     * penilaian draf ($components). Jadwal kosong = diatur nanti. Dijalankan dalam konteks periode baru.
     */
    private function createAssessmentSchedule(Period $period, array $windows, array $components): void
    {
        $errors = [];
        foreach (AssessmentComponent::query()->get() as $component) {
            $opens = trim((string) ($windows[$component->key]['opens_at'] ?? ''));
            $due = trim((string) ($windows[$component->key]['due_at'] ?? ''));
            if ($opens === '' && $due === '') {
                continue;
            }
            if ($opens === '' || $due === '' || strtotime($due) === false || strtotime($opens) === false) {
                $errors["windows.{$component->key}.due_at"] = "{$component->label}: isi waktu dibuka dan deadline, atau kosongkan keduanya.";
            } elseif (strtotime($due) <= strtotime($opens)) {
                $errors["windows.{$component->key}.due_at"] = "{$component->label}: deadline penilaian harus setelah waktu dibuka.";
            } else {
                $period->syncAssessmentWindow($component->key, $opens, $due);
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($components as $data) {
            if (AssessmentComponent::query()->where('label', $data['label'])->exists()) {
                throw ValidationException::withMessages(['draft_components' => "Bentuk penilaian {$data['label']} sudah ada pada data master yang disalin."]);
            }
            $component = AssessmentComponent::createForLecturers($data);
            $period->syncAssessmentWindow($component->key, $data['opens_at'], $data['due_at']);
        }
    }

    /**
     * Kartu "Akun admin prodi": akun yang langsung masuk ke Dashboard Periode ini. Email yang sama di beberapa periode
     * = satu akun (password berlaku untuk semuanya). Email kosong = periode tanpa admin prodi.
     *
     * @param  array{email: ?string, password: ?string}  $admin
     */
    private function syncAdmin(Period $period, array $admin): void
    {
        if (! $admin['email']) {
            $period->forceFill(['admin_user_id' => null])->save();

            return;
        }

        $user = User::query()->where('email', $admin['email'])->first();
        if (! $user) {
            $user = User::create(['name' => 'Admin '.$period->prodi_name, 'email' => $admin['email'], 'role' => 'admin_prodi',
                'password' => $admin['password'], 'is_active' => true]);
        } elseif ($admin['password'] && ! Hash::check($admin['password'], (string) $user->password)) {
            // Password diganti admin fakultas: perangkat yang masih memakai password lama dikeluarkan.
            $user->forceFill(['password' => $admin['password'], 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $period->forceFill(['admin_user_id' => $user->id])->save();
    }
}
