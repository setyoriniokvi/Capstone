<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Clo;
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
        DB::transaction(function () use ($screening, $topics, $qr) {
            $period = $this->period();
            $this->rules($period);
            $this->indicators();
            [$admin, $lecturers] = $this->people();
            $students = $this->students();

            LoadQuota::query()->updateOrCreate(['period_id' => $period->id, 'scope' => 'global', 'category' => null, 'lecturer_id' => null], [
                'supervise_default' => 5, 'supervise_min' => 2, 'supervise_max' => 8,
                'examine_default' => 6, 'examine_min' => 2, 'examine_max' => 8,
            ]);

            foreach ([['penilaian', 48], ['approval_logbook', 72], ['approval_pelamar', 48]] as [$type, $hours]) {
                ReminderRule::query()->updateOrCreate(['job_type' => $type, 'hours_before' => $hours], ['channels' => ['email', 'whatsapp'], 'is_active' => true]);
            }

            // Topik dosen di bank topik
            $bank = [
                ['dsn1', 'Integrasi data persediaan multi-gudang untuk perencanaan pengadaan', 'Supply Chain', 2, 'PT Sinar Distribusi Nusantara', 'DSC', 'Supply Chain Analytics'],
                ['dsn2', 'Dashboard pemantauan kinerja layanan akademik berbasis data terintegrasi', 'Sistem Informasi', 1, 'Direktorat Akademik Telkom University', 'SI', 'Enterprise Systems'],
                ['dsn3', 'Perancangan sistem penjadwalan produksi terintegrasi dengan perawatan mesin', 'Teknik Industri', 2, 'PT Tekstil Majalaya Jaya', 'TI', 'Production Systems'],
                ['dsn2', 'Platform pelaporan keuangan UMKM yang terhubung dengan sistem kasir', 'Sistem Informasi', 1, 'Koperasi UMKM Kota Bandung', 'SI', 'Data Engineering'],
            ];
            $bankTopics = [];
            foreach ($bank as $i => [$owner, $title, $field, $quota, $object, $prodi, $competencies]) {
                $bankTopics[] = Topic::create([
                    'period_id' => $period->id, 'source' => 'dosen', 'lecturer_id' => $lecturers[$owner]->id,
                    'title' => $title, 'field' => $field, 'quota' => $quota,
                    'object' => $object, 'prodi_code' => $prodi, 'competencies' => $competencies,
                    'description' => "Contoh topik dosen. {$title}. Tim diharapkan memetakan proses bisnis eksisting, merancang solusi sistem terintegrasi, lalu memverifikasi rancangan terhadap spesifikasi yang disepakati dengan problem owner.",
                    'status' => $i === 3 ? 'menunggu' : 'diterima',
                    'reviewed_by' => $i === 3 ? null : $admin->id, 'reviewed_at' => $i === 3 ? null : now()->subDays(6),
                ]);
            }

            // Tim 1 — diterima, topik dosen diterima, logbook 8 disetujui, proposal sudah dinilai
            $t1 = $this->team($period, 'KP-SI-01', 'Tim Arunika', 'SI', [$students['s1'], $students['s2'], $students['s3']], 'diterima', $admin, $screening, $qr);
            $this->independentTopic($t1, 'Sistem monitoring rantai pasok bahan baku UMKM kuliner', 'diterima', $admin, $topics);
            $app = TopicApplication::create(['topic_id' => $bankTopics[0]->id, 'team_id' => $t1->id, 'motivation' => 'Sesuai pengalaman KP di bagian gudang.']);
            $topics->decide($app, true, 'Selamat bergabung.', $lecturers['dsn1']);
            TeamLecturer::query()->updateOrCreate(['team_id' => $t1->id, 'role' => 'penguji'], ['lecturer_id' => $lecturers['dsn3']->id, 'source' => 'admin', 'assigned_by' => $admin->id]);
            $this->logbooks($t1, 9, 8, $lecturers['dsn1'], $students['s1']);
            $this->document($t1, $period->documentMilestone('proposal'), $students['s1']);
            $this->assess($t1, 'proposal', $lecturers['dsn1'], [4, 5]);

            // Tim 2 — diterima, topik mandiri diterima, pembimbing diplot admin
            $t2 = $this->team($period, 'KP-TI-01', 'Tim Lintang', 'TI', [$students['s4'], $students['s5']], 'diterima', $admin, $screening, $qr);
            $this->independentTopic($t2, 'Perancangan sistem penjadwalan perawatan mesin pada lini produksi', 'diterima', $admin, $topics);
            TeamLecturer::query()->updateOrCreate(['team_id' => $t2->id, 'role' => 'pembimbing'], ['lecturer_id' => $lecturers['dsn3']->id, 'source' => 'admin', 'assigned_by' => $admin->id]);
            $this->logbooks($t2, 3, 2, $lecturers['dsn3'], $students['s4']);
            TopicApplication::create(['topic_id' => $bankTopics[2]->id, 'team_id' => $t2->id, 'motivation' => 'Ingin memperdalam penjadwalan produksi.']);

            // Tim 3 — menunggu validasi admin
            $this->team($period, 'KP-DSC-01', 'Tim Samudra', 'DSC', [$students['s6'], $students['s7'], $students['s8']], 'menunggu', $admin, $screening, $qr);

            // Tim 4 — draft dengan undangan tertunda
            $t4 = $this->team($period, 'KP-SI-02', 'Tim Kirana', 'SI', [$students['s9']], 'draft', $admin, $screening, $qr);
            $t4->members()->create(['user_id' => $students['s10']->id, 'invite_status' => 'diundang']);

            // Tim 5 — diterima, topik mandiri masih menunggu
            $t5 = $this->team($period, 'KP-MR-01', 'Tim Bentala', 'MR', [$students['s11'], $students['s12']], 'diterima', $admin, $screening, $qr);
            $this->independentTopic($t5, 'Sistem informasi pengelolaan aset fasilitas kampus', 'menunggu', $admin, $topics);

            $this->supportFile($admin);
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
            ['Registrasi tim', 'registrasi_tim', null, 1, $week(1), $week(3, 6)->setTime(23, 59)],
            ['Pengajuan topik mandiri', 'pengajuan_topik', null, 2, $week(1), $week(4, 6)->setTime(23, 59)],
            ['Pendaftaran topik dosen', 'apply_topik_dosen', null, 3, $week(2), $week(5, 6)->setTime(23, 59)],
            ['Proposal', 'dokumen', 'proposal', 2, $week(1), $week(3, 4)->setTime(23, 59)],
            ['Bimbingan & logbook', 'bimbingan', null, 14, $week(2), $week(14, 4)->setTime(23, 59)],
            ['Laporan progress', 'dokumen', 'laporan_progress', 11, $week(9), $week(11, 4)->setTime(23, 59)],
            ['Laporan akhir', 'dokumen', 'laporan_akhir', 15, $week(13), $week(15, 2)->setTime(23, 59)],
            ['Materi presentasi', 'dokumen', 'materi_presentasi', 15, $week(14), $week(15, 2)->setTime(23, 59)],
            ['Sidang Capstone', 'sidang', null, 15, $week(15, 2), $week(15, 4)->setTime(17, 0)],
            ['Poster pameran', 'dokumen', 'poster', 16, $week(15), $week(16, 1)->setTime(23, 59)],
            ['Pameran prototipe', 'pameran', null, 16, $week(16, 2), $week(16, 3)->setTime(17, 0)],
            ['Peer review', 'peer_review', null, 16, $week(15), $week(16, 5)->setTime(23, 59)],
        ];
        foreach ($milestones as $i => [$name, $kind, $doc, $wk, $from, $to]) {
            $period->milestones()->create(['name' => $name, 'kind' => $kind, 'document_type' => $doc, 'week_no' => $wk, 'starts_at' => $from, 'due_at' => $to, 'sort_order' => $i]);
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
            'TI' => ['Production Systems', 'Supply Chain', 'Ergonomics', 'Quality Engineering'],
            'SI' => ['Enterprise Systems', 'Data Engineering', 'Supply Chain Analytics'],
            'DSC' => ['Logistics', 'Supply Chain Analytics'],
            'MR' => ['Engineering Management', 'Project Management'],
        ];
        // Satu aturan registrasi untuk periode; peminatan gabungan semua prodi contoh
        $rule = RegistrationRule::create(['period_id' => $period->id, 'min_sks' => 110]);
        $rule->courses()->create(['code' => 'KP', 'name' => 'Kerja Praktek (contoh)']);
        foreach (collect($specializations)->flatten()->unique() as $name) {
            $rule->specializations()->create(['name' => $name]);
        }
    }

    /** Indikator contoh: satu per CLO dengan pembagian bobot sama dengan CLO-nya. */
    private function indicators(): void
    {
        $schemes = ['CLO5' => 'peer_observasi', 'CLO6' => 'dokumen_lisan'];
        // Deskripsi tingkat CLO1–CLO3 diambil dari Rubrik Penilaian Capstone (Alur Tabel 3.3).
        $rubrics = [
            'CLO1' => ['Identifikasi akar permasalahan dan para pihak.', [
                5 => 'Mampu dengan logis mengidentifikasi akar masalah yang melibatkan lebih dari satu pihak dengan mempertimbangkan interaksi kepentingan para pihak yang relevan, dan didukung justifikasi empiris yang sesuai.',
                4 => 'Mampu dengan logis mengidentifikasi akar permasalahan yang melibatkan lebih dari satu pihak dengan mempertimbangkan interaksi kepentingan para pihak yang relevan, namun kurang didukung justifikasi empiris yang sesuai.',
                3 => 'Mampu mengidentifikasi akar dari permasalahan dan para pihak yang terlibat namun tidak mempertimbangkan interaksi kepentingan para pihak.',
                2 => 'Mampu mengidentifikasi gejala dari permasalahan dan dapat mengidentifikasi secara akurat para pihak yang terlibat.',
                1 => 'Mampu mengidentifikasi gejala dari permasalahan namun belum dapat mengidentifikasi secara akurat para pihak yang terlibat.',
            ]],
            'CLO2' => ['Pelibatan body of knowledge dan standar keteknikan.', [
                5 => 'Mampu merancang solusi yang relevan dengan melibatkan lebih dari 20% body of knowledge dengan standar keteknikan yang bersesuaian dan mempertimbangkan regulasi yang relevan.',
                4 => 'Mampu merancang solusi yang relevan dengan melibatkan lebih dari 20% body of knowledge dengan standar keteknikan yang bersesuaian atau mempertimbangkan regulasi yang relevan.',
                3 => 'Mampu merancang solusi yang relevan dengan melibatkan lebih dari 20% body of knowledge, namun tidak mempertimbangkan standar keteknikan ataupun regulasi yang relevan.',
                2 => 'Mampu merancang solusi dengan melibatkan paling banyak 20% body of knowledge, namun belum menyelesaikan permasalahan sepenuhnya.',
                1 => 'Mampu merancang solusi dengan melibatkan hanya satu body of knowledge, namun belum mampu menyelesaikan permasalahan sepenuhnya.',
            ]],
        ];

        foreach (Clo::query()->with('componentWeights')->get() as $clo) {
            $indicator = Indicator::query()->updateOrCreate(['code' => 'IND-'.substr($clo->code, 3)], [
                'clo_id' => $clo->id,
                'name' => '[Contoh] '.rtrim($clo->description, '.'),
                'evidence' => $clo->assessment_scheme,
                'weight' => $clo->weight,
                'scheme' => $schemes[$clo->code] ?? 'dokumen',
            ]);
            $indicator->componentWeights()->delete();
            foreach ($clo->componentWeights as $w) {
                $indicator->componentWeights()->create(['component' => $w->component, 'weight' => $w->weight]);
            }
            if (isset($rubrics[$clo->code])) {
                [$points, $levels] = $rubrics[$clo->code];
                $indicator->rubric()->updateOrCreate([], [
                    'assessment_points' => $points,
                    'level_5' => $levels[5], 'level_4' => $levels[4], 'level_3' => $levels[3], 'level_2' => $levels[2], 'level_1' => $levels[1],
                    'level_0' => 'Tidak mengumpulkan.',
                ]);
            }
        }
    }

    /** @return array{0: User, 1: array<string, User>} */
    private function people(): array
    {
        $password = config('capstone.demo_password');
        $admin = User::query()->updateOrCreate(['email' => 'admin@gmail.com'], ['name' => 'Admin Capstone', 'role' => 'admin', 'prodi_code' => 'SI', 'password' => $password]);

        $data = [
            'dsn1' => ['Dr. Ratna Wulandari', 'RWL', 'SI', 'Supply Chain Analytics', '081200000001', [1, 1, 1, 1]],
            'dsn2' => ['Hendra Saputra, M.T.', 'HSP', 'SI', 'Enterprise Systems', '081200000002', [1, 1, 1, 0]],
            'dsn3' => ['Dr. Maya Kartika', 'MKT', 'TI', 'Production Systems', '081200000003', [1, 1, 1, 1]],
            'dsn4' => ['Yusuf Pratama, M.Sc.', 'YPR', 'DSC', 'Logistics', '', [0, 1, 1, 0]],
            'dsn5' => ['Sinta Maharani, M.M.', 'SMH', 'MR', 'Engineering Management', '081200000005', [0, 0, 1, 1]],
        ];
        $lecturers = [];
        foreach ($data as $key => [$name, $code, $prodi, $spec, $phone, [$propose, $supervise, $examine, $class]]) {
            $user = User::query()->updateOrCreate(['email' => $this->demoEmail('dosen', $key)], ['name' => $name, 'role' => 'dosen', 'prodi_code' => $prodi, 'phone' => $phone ?: null, 'password' => $password]);
            LecturerProfile::query()->updateOrCreate(['user_id' => $user->id], [
                'lecturer_code' => $code, 'specialization' => $spec,
                'can_propose_topic' => (bool) $propose, 'can_supervise' => (bool) $supervise, 'can_examine' => (bool) $examine, 'is_class_lecturer' => (bool) $class,
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
            ['s1', 'Alya Rahmadani', 'SI', 128, 'Jawa Barat', 'Enterprise Systems'],
            ['s2', 'Bima Nugraha', 'SI', 124, 'Sumatera Utara', 'Data Engineering'],
            ['s3', 'Citra Ayuningtyas', 'TI', 130, 'Jawa Tengah', 'Supply Chain'],
            ['s4', 'Dimas Prakoso', 'TI', 126, 'Jawa Timur', 'Production Systems'],
            ['s5', 'Eka Larasati', 'TI', 121, 'Kalimantan Timur', 'Ergonomics'],
            ['s6', 'Farhan Akbar', 'DSC', 119, 'Sulawesi Selatan', 'Logistics'],
            ['s7', 'Gita Permatasari', 'DSC', 122, 'Sulawesi Selatan', 'Logistics'],
            ['s8', 'Hafiz Ramadhan', 'DSC', 116, 'Riau', 'Logistics'],
            ['s9', 'Indah Puspita', 'SI', 127, 'Bali', 'Enterprise Systems'],
            ['s10', 'Joko Santoso', 'SI', 112, 'DI Yogyakarta', 'Data Engineering'],
            ['s11', 'Kevin Wijaya', 'MR', 125, 'Banten', 'Engineering Management'],
            ['s12', 'Laras Sekar', 'MR', 120, 'Papua', 'Project Management'],
            ['s13', 'Mira Anggraini', 'SI', 98, 'Jawa Barat', 'Enterprise Systems'],
            ['s14', 'Naufal Hakim', 'TI', 131, 'Aceh', 'Quality Engineering'],
        ];

        $students = [];
        foreach ($rows as $i => [$key, $name, $prodi, $sks, $region, $spec]) {
            $students[$key] = User::query()->updateOrCreate(['email' => $this->demoEmail('mahasiswa', $key)], [
                'name' => $name, 'role' => 'mahasiswa', 'prodi_code' => $prodi, 'password' => config('capstone.demo_password'),
                'nim' => '1020223'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'sks_passed' => $sks, 'passed_courses' => $sks >= 110 ? ['KP'] : [],
                'origin_region' => $region, 'specialization' => $spec, 'phone' => '0857000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
            ]);
        }

        return $students;
    }

    private function team(Period $period, string $code, string $name, string $prodi, array $members, string $status, User $admin, ScreeningService $screening, QrService $qr): Team
    {
        $team = Team::create([
            'period_id' => $period->id, 'code' => $code, 'name' => $name, 'prodi_code' => $prodi,
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
        $path = $this->placeholderFile("documents/{$team->period_id}/{$team->code}/{$owner->id}/proposal_r1.pdf", "Proposal contoh {$team->code}");
        $document = Document::create([
            'team_id' => $team->id, 'milestone_id' => $milestone->id, 'owner_id' => $owner->id, 'owner_email' => $owner->email,
            'document_type' => 'proposal', 'review_status' => 'ditinjau',
        ]);
        $version = $document->versions()->create([
            'revision_no' => 1, 'original_name' => 'proposal.pdf', 'stored_name' => "{$owner->nim}_".Str::slug($owner->name).'_proposal_r1.pdf',
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
            'title' => 'Panduan Capstone Design & Project (contoh)', 'category' => 'Panduan',
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
