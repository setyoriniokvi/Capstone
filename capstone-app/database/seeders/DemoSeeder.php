<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Document;
use App\Models\Indicator;
use App\Models\LecturerProfile;
use App\Models\LoadQuota;
use App\Models\Logbook;
use App\Models\Period;
use App\Models\RegistrationRule;
use App\Models\ReminderRule;
use App\Models\SupportFile;
use App\Models\Team;
use App\Models\TeamLecturer;
use App\Models\Topic;
use App\Models\TopicApplication;
use App\Models\User;
use App\Services\DocumentService;
use App\Services\QrService;
use App\Services\ScreeningService;
use App\Services\TopicService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DATA DEMO — seluruh nama, email (@gmail.com), topik, dan indikator di sini
 * adalah contoh untuk mencoba alur aplikasi, bukan data resmi. Jangan dijalankan di produksi.
 *
 *   php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(ScreeningService $screening, TopicService $topics, QrService $qr): void
    {
        if (Team::query()->where('code', 'KP-SI-01')->exists()) {
            $this->command?->warn('Data demo sudah ada. Untuk mengulang: php artisan migrate:fresh --seed lalu jalankan DemoSeeder lagi.');

            return;
        }

        DB::transaction(function () use ($screening, $topics, $qr) {
            $period = $this->period();
            $this->rules($period);
            [$admin, $lecturers] = $this->people();
            $students = $this->students();

            LoadQuota::query()->updateOrCreate(['period_id' => $period->id, 'scope' => 'global', 'category' => null, 'lecturer_id' => null], [
                'supervise_min' => 2, 'supervise_max' => 8,
                'examine_min' => 2, 'examine_max' => 8,
            ]);

            foreach ([['penilaian', 48], ['approval_logbook', 72], ['approval_pelamar', 48]] as [$type, $hours]) {
                ReminderRule::query()->updateOrCreate(['job_type' => $type, 'hours_before' => $hours], ['channels' => ['email', 'whatsapp'], 'is_active' => true]);
            }

            // Topik dosen di bank topik: [pengusul utama, judul, bidang, kuota, objek, prodi, peminatan, kompetensi, pengusul bersama]
            $bank = [
                ['dsn1', 'Integrasi data persediaan multi-gudang untuk perencanaan pengadaan', 'Supply Chain', 2, 'PT Sinar Distribusi Nusantara', ['DSC', 'TI'], ['Enterprise Intelligence System Development', 'Enterprise Resource Planning'],
                    "Analisis data persediaan dan peramalan permintaan.\nPemodelan proses bisnis pengadaan.", ['dsn4']],
                ['dsn2', 'Dashboard pemantauan kinerja layanan akademik berbasis data terintegrasi', 'Sistem Informasi', 1, 'Direktorat Akademik Telkom University', ['SI'], ['Enterprise Intelligence System Development'],
                    "Perancangan basis data dan integrasi sistem.\nVisualisasi data dashboard.", []],
                ['dsn3', 'Perancangan sistem penjadwalan produksi terintegrasi dengan perawatan mesin', 'Teknik Industri', 2, 'PT Tekstil Majalaya Jaya', ['TI'], ['Enterprise Infrastructure Management'],
                    "Penjadwalan produksi dan perencanaan perawatan.\nSimulasi sistem produksi.", []],
                ['dsn2', 'Platform pelaporan keuangan UMKM yang terhubung dengan sistem kasir', 'Sistem Informasi', 1, 'Koperasi UMKM Kota Bandung', ['SI'], ['Enterprise Data Management'],
                    "Integrasi API sistem kasir.\nPengolahan data transaksi.", []],
            ];
            $bankTopics = [];
            foreach ($bank as $i => [$owner, $title, $field, $quota, $object, $prodi, $specializations, $competencies, $coProposers]) {
                $bankTopics[] = $topic = Topic::create([
                    'period_id' => $period->id, 'source' => 'dosen', 'lecturer_id' => $lecturers[$owner]->id,
                    'title' => $title, 'field' => $field, 'quota' => $quota,
                    'object' => $object, 'prodi_codes' => $prodi, 'specializations' => $specializations, 'competencies' => $competencies,
                    'description' => "Contoh topik dosen. {$title}. Tim diharapkan memetakan proses bisnis eksisting, merancang solusi sistem terintegrasi, lalu memverifikasi rancangan terhadap spesifikasi yang disepakati dengan problem owner.",
                    'status' => $i === 3 ? 'menunggu' : 'diterima',
                    'reviewed_by' => $i === 3 ? null : $admin->id, 'reviewed_at' => $i === 3 ? null : now()->subDays(6),
                ]);
                $topic->proposers()->attach($lecturers[$owner]->id);
                foreach ($coProposers as $co) {
                    $topic->proposers()->attach($lecturers[$co]->id, ['invited_by' => $lecturers[$owner]->id]);
                }
            }

            // Tim 1 — diterima, topik dosen diterima, logbook 8 disetujui, proposal sudah dinilai
            $t1 = $this->team($period, 'Tim Arunika', [$students['s1'], $students['s2'], $students['s3']], 'diterima', $admin, $screening, $qr);
            $this->independentTopic($t1, 'Sistem monitoring rantai pasok bahan baku UMKM kuliner', 'diterima', $admin, $topics);
            $app = TopicApplication::create(['topic_id' => $bankTopics[0]->id, 'team_id' => $t1->id, 'motivation' => 'Sesuai pengalaman KP di bagian gudang.']);
            $topics->decide($app, true, 'Selamat bergabung.', $lecturers['dsn1']);
            TeamLecturer::query()->updateOrCreate(['team_id' => $t1->id, 'role' => 'penguji'], ['lecturer_id' => $lecturers['dsn3']->id, 'source' => 'admin', 'assigned_by' => $admin->id]);
            $this->logbooks($t1, 9, 8, $lecturers['dsn1'], $students['s1']);
            $this->document($t1, $period->documentMilestone('proposal'), $students['s1']);
            $this->assess($t1, 'proposal', $lecturers['dsn1'], [4, 5]);

            // Tim 2 — diterima, topik mandiri diterima, pembimbing diplot admin
            $t2 = $this->team($period, 'Tim Lintang', [$students['s4'], $students['s5']], 'diterima', $admin, $screening, $qr);
            $this->independentTopic($t2, 'Perancangan sistem penjadwalan perawatan mesin pada lini produksi', 'diterima', $admin, $topics);
            TeamLecturer::query()->updateOrCreate(['team_id' => $t2->id, 'role' => 'pembimbing'], ['lecturer_id' => $lecturers['dsn3']->id, 'source' => 'admin', 'assigned_by' => $admin->id]);
            $this->logbooks($t2, 3, 2, $lecturers['dsn3'], $students['s4']);
            TopicApplication::create(['topic_id' => $bankTopics[2]->id, 'team_id' => $t2->id, 'motivation' => 'Ingin memperdalam penjadwalan produksi.']);

            // Tim 3 — menunggu validasi admin
            $this->team($period, 'Tim Samudra', [$students['s6'], $students['s7'], $students['s8']], 'menunggu', $admin, $screening, $qr);

            // Tim 4 — draft dengan undangan tertunda
            $t4 = $this->team($period, 'Tim Kirana', [$students['s9']], 'draft', $admin, $screening, $qr);
            $t4->members()->create(['user_id' => $students['s10']->id, 'invite_status' => 'diundang']);

            // Tim 5 — diterima, topik mandiri masih menunggu
            $t5 = $this->team($period, 'Tim Bentala', [$students['s11'], $students['s12']], 'diterima', $admin, $screening, $qr);
            $this->independentTopic($t5, 'Sistem informasi pengelolaan aset fasilitas kampus', 'menunggu', $admin, $topics);

            $this->supportFile($admin);

            // Hak akses Pembimbing / Penguji mengikuti plotting demo (pada aplikasi, tercentang otomatis saat diplot).
            foreach (TeamLecturer::query()->get() as $assignment) {
                LecturerProfile::grant($assignment->lecturer_id, $assignment->role);
            }
        });
    }

    private function period(): Period
    {
        Period::query()->update(['is_active' => false]);
        $start = now()->startOfWeek()->subWeek(); // minggu berjalan = minggu ke-2
        $period = Period::create([
            'name' => 'Capstone Demo '.$start->year.'/'.($start->year + 1),
            'academic_year' => $start->year.'/'.($start->year + 1),
            'prodi_name' => 'S1 Sistem Informasi',
            'semester' => 'ganjil',
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addWeeks(16)->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $week = fn (int $w, int $day = 0) => $start->copy()->addWeeks($w - 1)->addDays($day);
        $milestones = [
            ['Registrasi tim', 'registrasi_tim', null, $week(1), $week(3, 6)->setTime(23, 59)],
            ['Pengajuan topik mandiri', 'pengajuan_topik', null, $week(1), $week(4, 6)->setTime(23, 59)],
            ['Pendaftaran topik dosen', 'apply_topik_dosen', null, $week(2), $week(5, 6)->setTime(23, 59)],
            ['Proposal', 'dokumen', 'proposal', $week(1), $week(3, 4)->setTime(23, 59)],
            ['Bimbingan & logbook', 'bimbingan', null, $week(2), $week(14, 4)->setTime(23, 59)],
            ['Laporan progress', 'dokumen', 'laporan_progress', $week(9), $week(11, 4)->setTime(23, 59)],
            ['Laporan akhir', 'dokumen', 'laporan_akhir', $week(13), $week(15, 2)->setTime(23, 59)],
            ['Materi presentasi', 'dokumen', 'materi_presentasi', $week(14), $week(15, 2)->setTime(23, 59)],
            ['Sidang Capstone', 'sidang', null, $week(15, 2), $week(15, 4)->setTime(17, 0)],
            ['Poster pameran', 'dokumen', 'poster', $week(15), $week(16, 1)->setTime(23, 59)],
            ['Pameran prototipe', 'pameran', null, $week(16, 2), $week(16, 3)->setTime(17, 0)],
        ];
        foreach ($milestones as $i => [$name, $kind, $doc, $from, $to]) {
            $period->milestones()->create(['name' => $name, 'kind' => $kind, 'document_type' => $doc, 'starts_at' => $from, 'due_at' => $to, 'sort_order' => $i]);
        }

        foreach ([
            'proposal' => [$week(2), $week(4, 6)->setTime(23, 59)],
            'laporan_progress' => [$week(11), $week(12, 6)->setTime(23, 59)],
            'sidang_pembimbing' => [$week(15), $week(16, 6)->setTime(23, 59)],
            'sidang_penguji' => [$week(15), $week(16, 6)->setTime(23, 59)],
            'pameran' => [$week(16), $week(16, 6)->setTime(23, 59)],
        ] as $component => [$open, $due]) {
            $period->assessmentWindows()->create(['component' => $component, 'opens_at' => $open, 'due_at' => $due]);
        }

        return $period;
    }

    private function rules(Period $period): void
    {
        $specializations = [
            'Enterprise Intelligence System Development',
            'Enterprise Data Management',
            'System Architecture and Governance',
            'Enterprise Resource Planning',
            'Enterprise Infrastructure Management',
        ];
        // Satu aturan registrasi untuk periode beserta daftar peminatannya
        $rule = RegistrationRule::create(['period_id' => $period->id, 'min_sks' => 110]);
        $rule->courses()->create(['code' => 'KP', 'name' => 'Kerja Praktek (contoh)']);
        foreach ($specializations as $name) {
            $rule->specializations()->create(['name' => $name]);
        }
    }

    /** @return array{0: User, 1: array<string, User>} */
    private function people(): array
    {
        $password = config('capstone.demo_password');
        $admin = User::query()->updateOrCreate(['email' => 'admin@gmail.com'], ['name' => 'Admin Capstone', 'role' => 'admin', 'password' => $password]);

        $data = [
            'dsn1' => ['Dr. Ratna Wulandari', 'RWL', 'Enterprise Intelligence System Development', '081200000001'],
            'dsn2' => ['Hendra Saputra, M.T.', 'HSP', 'Enterprise Intelligence System Development', '081200000002'],
            'dsn3' => ['Dr. Maya Kartika', 'MKT', 'Enterprise Infrastructure Management', '081200000003'],
            'dsn4' => ['Yusuf Pratama, M.Sc.', 'YPR', 'Enterprise Resource Planning', ''],
            'dsn5' => ['Sinta Maharani, M.M.', 'SMH', 'System Architecture and Governance', '081200000005'],
        ];
        $lecturers = [];
        foreach ($data as $key => [$name, $code, $spec, $phone]) {
            $user = User::query()->updateOrCreate(['email' => $this->demoEmail('dosen', $key)], ['name' => $name, 'role' => 'dosen', 'phone' => $phone ?: null, 'password' => $password]);
            LecturerProfile::query()->updateOrCreate(['user_id' => $user->id], [
                'lecturer_code' => $code, 'specialization' => $spec,
            ]);
            $lecturers[$key] = $user;
        }

        return [$admin, $lecturers];
    }

    /** dsn1 → dosen@gmail.com, dsn2 → dosen2@gmail.com; s1 → mahasiswa@gmail.com, s2 → mahasiswa2@gmail.com, dst. */
    private function demoEmail(string $role, string $key): string
    {
        $no = (int) preg_replace('/\D/', '', $key);

        return $role.($no > 1 ? $no : '').'@gmail.com';
    }

    /** @return array<string, User> */
    private function students(): array
    {
        $rows = [
            ['s1', 'Alya Rahmadani', 128, 'Jawa Barat', 'Enterprise Intelligence System Development'],
            ['s2', 'Bima Nugraha', 124, 'Sumatera Utara', 'Enterprise Data Management'],
            ['s3', 'Citra Ayuningtyas', 130, 'Jawa Tengah', 'Enterprise Resource Planning'],
            ['s4', 'Dimas Prakoso', 126, 'Jawa Timur', 'Enterprise Infrastructure Management'],
            ['s5', 'Eka Larasati', 121, 'Kalimantan Timur', 'System Architecture and Governance'],
            ['s6', 'Farhan Akbar', 119, 'Sulawesi Selatan', 'Enterprise Resource Planning'],
            ['s7', 'Gita Permatasari', 122, 'Sulawesi Selatan', 'Enterprise Resource Planning'],
            ['s8', 'Hafiz Ramadhan', 116, 'Riau', 'Enterprise Resource Planning'],
            ['s9', 'Indah Puspita', 127, 'Bali', 'Enterprise Intelligence System Development'],
            ['s10', 'Joko Santoso', 112, 'DI Yogyakarta', 'Enterprise Data Management'],
            ['s11', 'Kevin Wijaya', 125, 'Banten', 'System Architecture and Governance'],
            ['s12', 'Laras Sekar', 120, 'Papua', 'Enterprise Resource Planning'],
            ['s13', 'Mira Anggraini', 98, 'Jawa Barat', 'Enterprise Intelligence System Development'],
            ['s14', 'Naufal Hakim', 131, 'Aceh', 'System Architecture and Governance'],
        ];

        $students = [];
        foreach ($rows as $i => [$key, $name, $sks, $region, $spec]) {
            $students[$key] = User::query()->updateOrCreate(['email' => $this->demoEmail('mahasiswa', $key)], [
                'name' => $name, 'role' => 'mahasiswa', 'password' => config('capstone.demo_password'),
                'nim' => '1020223'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'sks_passed' => $sks, 'passed_courses' => $sks >= 110 ? ['KP'] : [],
                'origin_region' => $region, 'specialization' => $spec, 'phone' => '0857000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]);
        }

        return $students;
    }

    private function team(Period $period, string $name, array $members, string $status, User $admin, ScreeningService $screening, QrService $qr): Team
    {
        $team = Team::create([
            'period_id' => $period->id, 'name' => $name,
            'leader_id' => $members[0]->id, 'status' => $status,
            'submitted_at' => $status === 'draft' ? null : now()->subDays(8),
            'reviewed_by' => $status === 'diterima' ? $admin->id : null,
            'reviewed_at' => $status === 'diterima' ? now()->subDays(7) : null,
        ]);
        foreach ($members as $i => $user) {
            $team->members()->create([
                'user_id' => $user->id, 'is_leader' => $i === 0, 'invite_status' => 'diterima', 'responded_at' => now()->subDays(9),
                'khs_path' => $this->placeholderFile("registrations/{$team->id}/{$user->id}/khs.pdf", "KHS contoh {$user->name}"),
                'domicile_path' => $this->placeholderFile("registrations/{$team->id}/{$user->id}/domisili.pdf", "Domisili contoh {$user->name}"),
            ]);
        }
        $screening->apply($team);
        if ($status === 'diterima') {
            $qr->ensureToken($team);
        }

        return $team;
    }

    private function independentTopic(Team $team, string $title, string $status, User $admin, TopicService $topics): void
    {
        Topic::create([
            'period_id' => $team->period_id, 'source' => 'mandiri', 'team_id' => $team->id, 'title' => $title,
            'description' => "Contoh topik mandiri hasil Kerja Praktek. {$title}. Permasalahan melibatkan beberapa unit kerja yang datanya belum terhubung sehingga perlu solusi sistem terintegrasi.",
            'field' => 'Sistem terintegrasi', 'object' => 'UMKM mitra Kerja Praktek tim '.$team->name, 'status' => $status,
            'reviewed_by' => $status === 'menunggu' ? null : $admin->id, 'reviewed_at' => $status === 'menunggu' ? null : now()->subDays(5),
        ]);
        $topics->refreshFinalTopic($team);
    }

    private function logbooks(Team $team, int $total, int $approved, User $lecturer, User $author): void
    {
        for ($i = 1; $i <= $total; $i++) {
            $log = Logbook::create([
                'team_id' => $team->id, 'session_no' => $i, 'session_date' => now()->subDays(($total - $i) * 2 + 1)->toDateString(),
                'activity' => "Sesi {$i}: diskusi kemajuan perancangan dan tindak lanjut bersama pembimbing.",
                'responsible_person' => $author->name, 'output' => "Catatan tindak lanjut sesi {$i}.",
                'status' => $i <= $approved ? 'disetujui' : 'menunggu',
                'reviewed_by' => $i <= $approved ? $lecturer->id : null, 'reviewed_at' => $i <= $approved ? now()->subDays($total - $i) : null,
                'created_by' => $author->id,
            ]);
            $log->snapshot('dibuat', $author);
        }
    }

    private function document(Team $team, $milestone, User $owner): void
    {
        $path = $this->placeholderFile("documents/{$team->period_id}/tim-{$team->id}/proposal_r1.pdf", "Proposal contoh {$team->name}");
        $document = Document::create([
            'team_id' => $team->id, 'milestone_id' => $milestone->id, 'owner_id' => $owner->id, 'owner_email' => $owner->email,
            'document_type' => 'proposal', 'review_status' => 'ditinjau',
        ]);
        $version = $document->versions()->create([
            'revision_no' => 1, 'original_name' => 'proposal.pdf', 'stored_name' => DocumentService::fileName($milestone->name, $team->name, 'pdf'),
            'mime' => 'application/pdf', 'size' => Storage::disk('local')->size($path), 'local_path' => $path,
            'checksum' => hash('sha256', Storage::disk('local')->get($path)), 'idempotency_key' => (string) Str::uuid(),
            'sync_action' => 'create', 'sync_status' => 'menunggu', 'uploaded_by' => $owner->id,
        ]);
        $document->update(['current_version_id' => $version->id, 'current_revision' => 1]);
    }

    private function assess(Team $team, string $component, User $lecturer, array $scores): void
    {
        $assessment = Assessment::create([
            'team_id' => $team->id, 'assessor_id' => $lecturer->id, 'component' => $component, 'assessor_role' => 'pembimbing',
            'status' => 'final', 'feedback' => 'Contoh feedback: perkuat data pendukung pada latar belakang.', 'submitted_at' => now()->subDay(),
        ]);
        $indicators = Indicator::query()->whereHas('componentWeights', fn ($q) => $q->where('component', $component))->orderBy('code')->get();
        foreach ($indicators as $i => $indicator) {
            $assessment->scores()->create(['indicator_id' => $indicator->id, 'score' => $scores[$i] ?? 4, 'factor' => 1]);
        }
    }

    private function supportFile(User $admin): void
    {
        $path = $this->placeholderFile('support-files/panduan-capstone-contoh.pdf', 'Panduan Capstone (contoh)');
        SupportFile::create([
            'title' => 'Panduan Capstone Design & Project (contoh)', 'category' => 'Panduan', 'visible_to' => ['dosen', 'mahasiswa'],
            'description' => 'Berkas contoh. Ganti dengan panduan resmi.', 'path' => $path,
            'original_name' => 'panduan-capstone.pdf', 'mime' => 'application/pdf', 'size' => Storage::disk('local')->size($path), 'uploaded_by' => $admin->id,
        ]);
    }

    /** PDF satu halaman minimal agar tautan unduh pada data demo dapat dibuka. */
    private function placeholderFile(string $path, string $text): string
    {
        $text = preg_replace('/[^A-Za-z0-9 .,\-]/', '', $text);
        $stream = "BT /F1 18 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        Storage::disk('local')->put($path, $pdf);

        return $path;
    }
}
