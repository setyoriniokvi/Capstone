<?php

namespace Database\Seeders;

use App\Models\Assessment;
use App\Models\Document;
use App\Models\DriveOperation;
use App\Models\LecturerProfile;
use App\Models\LoadQuota;
use App\Models\Logbook;
use App\Models\Milestone;
use App\Models\OralRubricLevel;
use App\Models\PeerReview;
use App\Models\Period;
use App\Models\RegistrationRule;
use App\Models\ReminderDispatch;
use App\Models\SupportFile;
use App\Models\Team;
use App\Models\TeamLecturer;
use App\Models\Topic;
use App\Models\TopicApplication;
use App\Models\User;
use App\Notifications\SystemNotice;
use App\Services\DocumentService;
use App\Services\QrService;
use App\Services\ScoreCalculator;
use App\Services\ScreeningService;
use App\Services\TopicService;
use App\Support\PeriodContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * DATA DEMO UNTUK PRESENTASI — periode "Capstone Showcase" yang berjalan paralel dengan periode lain (data lama tidak
 * diubah). Seluruh nama, email (@demo.test), dan topik adalah contoh; email .test tidak dapat menerima surat dan nomor WA
 * dikosongkan, jadi aksi demo tidak mengirim email / WhatsApp ke orang sungguhan. Periode berada di minggu ke-14 dengan
 * setiap proses berisi data: registrasi (menunggu / diterima / ditolak / draft), topik mandiri & topik dosen (menunggu /
 * diterima / ditolak), pelamar topik, plotting, kuota, log bimbingan, dokumen & sinkronisasi, penilaian (final / draft /
 * belum), peer review, rekap nilai lengkap, reminder, notifikasi, file pendukung.
 *
 *   php artisan db:seed --class=ShowcaseSeeder      (sekali saja; dijalankan ulang → ditolak)
 */
class ShowcaseSeeder extends Seeder
{
    public const PERIOD_NAME = 'Capstone Showcase 2026/2027';

    public const DOMAIN = 'demo.test';

    private Period $period;

    private User $admin;

    private User $prodiAdmin;

    /** @var array<string, User> */
    private array $lecturers = [];

    /** @var array<string, User> */
    private array $students = [];

    private Carbon $start;

    public function run(ScreeningService $screening, TopicService $topics, QrService $qr): void
    {
        if (Period::query()->withoutGlobalScopes()->where('name', self::PERIOD_NAME)->exists()) {
            throw new RuntimeException('Periode '.self::PERIOD_NAME.' sudah ada. Data demo tidak diisi dua kali.');
        }

        DB::transaction(function () use ($screening, $topics, $qr) {
            $this->period = $this->createPeriod();
            PeriodContext::using($this->period, function () use ($screening, $topics, $qr) {
                $this->rules();
                $this->people();
                $this->quotas();
                $bank = $this->bankTopics();
                $this->teams($bank, $screening, $topics, $qr);
                $this->supportFiles();
                $this->reminders();
            });
        });
    }

    // ------------------------------------------------------------------ periode & aturan

    private function createPeriod(): Period
    {
        // Minggu berjalan = minggu ke-14 dari 16
        $this->start = now()->startOfWeek()->subWeeks(13);
        $start = $this->start;
        $period = new Period([
            'name' => self::PERIOD_NAME,
            'academic_year' => '2026/2027',
            'prodi_name' => 'S1 Sistem Informasi',
            'semester' => 'ganjil',
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addWeeks(16)->subDay()->toDateString(),
            'is_active' => true,
            'min_approved_logbooks' => 8,
        ]);
        $period->masterSource = null; // master data dari template (CLO, indikator, rubrik, bentuk penilaian, dll.)
        $period->save();

        $week = fn (int $w, int $day = 0) => $start->copy()->addWeeks($w - 1)->addDays($day);
        // Registrasi & pengajuan topik sengaja masih dibuka (gelombang 2) agar dapat didemokan langsung.
        $milestones = [
            ['Registrasi tim', 'registrasi_tim', null, $week(1), $week(15, 6)->setTime(23, 59)],
            ['Pengajuan topik mandiri', 'pengajuan_topik', null, $week(1), $week(15, 6)->setTime(23, 59)],
            ['Pendaftaran topik dosen', 'apply_topik_dosen', null, $week(2), $week(15, 6)->setTime(23, 59)],
            ['Proposal', 'dokumen', 'proposal', $week(1), $week(3, 4)->setTime(23, 59)],
            ['Bimbingan & logbook', 'bimbingan', null, $week(2), $week(15, 4)->setTime(23, 59)],
            ['Laporan progress', 'dokumen', 'laporan_progress', $week(9), $week(11, 4)->setTime(23, 59)],
            ['Laporan akhir', 'dokumen', 'laporan_akhir', $week(13), $week(15, 2)->setTime(23, 59)],
            ['Materi presentasi', 'dokumen', 'materi_presentasi', $week(13), $week(15, 2)->setTime(23, 59)],
            ['Peer review', 'peer_review', null, $week(13), $week(15, 6)->setTime(23, 59)],
            ['Sidang Capstone', 'sidang', null, $week(15, 2), $week(15, 4)->setTime(17, 0)],
            ['Poster pameran', 'dokumen', 'poster', $week(15), $week(16, 1)->setTime(23, 59)],
            ['Pameran prototipe', 'pameran', null, $week(16, 2), $week(16, 3)->setTime(17, 0)],
        ];
        foreach ($milestones as $i => [$name, $kind, $doc, $from, $to]) {
            $period->milestones()->create(['name' => $name, 'kind' => $kind, 'document_type' => $doc, 'starts_at' => $from, 'due_at' => $to, 'sort_order' => $i]);
        }

        // Semua jendela penilaian dibuka sampai akhir periode agar dosen dapat mencoba menilai saat demo.
        foreach (['proposal', 'laporan_progress', 'sidang_pembimbing', 'sidang_penguji', 'pameran'] as $component) {
            $period->assessmentWindows()->create(['component' => $component, 'opens_at' => $week(2), 'due_at' => $week(16, 6)->setTime(23, 59)]);
        }

        return $period;
    }

    private function rules(): void
    {
        $rule = RegistrationRule::create([
            'period_id' => $this->period->id, 'min_sks' => 110, 'min_members' => 2, 'max_members' => 3,
            'require_distinct_region' => false, 'require_distinct_specialization' => false,
            'classes' => [...array_map(fn ($n) => sprintf('SI-47-%02d', $n), range(1, 10)), 'SI-47-INT'],
        ]);
        $rule->courses()->create(['code' => 'KP', 'name' => 'Kerja Praktek']);
        foreach (self::SPECIALIZATIONS as $name) {
            $rule->specializations()->create(['name' => $name]);
        }
    }

    private const SPECIALIZATIONS = [
        'Enterprise Intelligence System Development',
        'Enterprise Data Management',
        'System Architecture and Governance',
        'Enterprise Resource Planning',
        'Enterprise Infrastructure Management',
    ];

    // ------------------------------------------------------------------ akun

    private function people(): void
    {
        $password = config('capstone.demo_password');
        // Admin fakultas yang sudah ada (CRUD semua periode); admin prodi baru khusus periode showcase.
        $this->admin = User::query()->where('role', 'admin')->orderBy('id')->first()
            ?? User::create(['name' => 'Admin Fakultas', 'email' => 'fakultas@'.self::DOMAIN, 'role' => 'admin', 'password' => $password]);
        $this->prodiAdmin = User::create(['name' => 'Admin Prodi S1 SI (Showcase)', 'email' => 'prodi@'.self::DOMAIN, 'role' => 'admin_prodi', 'password' => $password]);
        $this->period->forceFill(['admin_user_id' => $this->prodiAdmin->id])->save();

        // [kunci, nama, kode, NIP, peminatan, pembimbing, penguji, dosen kelas, password]
        $rows = [
            ['andi', 'Dr. Andi Wijaya, S.T., M.T.', 'ADW', '198201012008011001', 'Enterprise Intelligence System Development', true, true, true, $password],
            ['rina', 'Rina Kusuma, S.Kom., M.Kom.', 'RKS', '198503152010122002', 'Enterprise Data Management', true, true, false, $password],
            ['budi', 'Dr. Budi Santoso, M.Sc.', 'BST', '197907202005011003', 'System Architecture and Governance', false, true, false, $password],
            ['dewi', 'Dewi Lestari, S.T., M.T.', 'DLS', '198811052014042004', 'Enterprise Resource Planning', true, false, true, $password],
            ['fajar', 'Fajar Nugroho, S.Kom., M.Sc.', 'FNG', '199002102018031005', 'Enterprise Infrastructure Management', true, true, true, $password],
            // Akun hasil impor: masih memakai password bawaan 12345678 (banner "Ganti password" tampil)
            ['sari', 'Sari Utami, S.E., M.M.', 'SUT', '199104252019032006', 'Enterprise Resource Planning', true, true, false, config('capstone.lecturer_default_password')],
        ];
        foreach ($rows as [$key, $name, $code, $nip, $spec, $supervise, $examine, $class, $pw]) {
            $user = User::create(['name' => $name, 'email' => "{$key}@".self::DOMAIN, 'role' => 'dosen', 'password' => $pw]);
            LecturerProfile::create([
                'user_id' => $user->id, 'lecturer_code' => $code, 'nip' => $nip, 'specialization' => $spec,
                'can_supervise' => $supervise, 'can_examine' => $examine, 'is_class_lecturer' => $class,
            ]);
            $user->joinPeriod($this->period);
            $this->lecturers[$key] = $user;
        }

        // [no, nama, kelas, SKS, asal, peminatan, nilai KP]
        $rows = [
            [1, 'Alya Rahmadani', 'SI-47-01', 128, 'Kota Bandung, Jawa Barat', 0, 'A'],
            [2, 'Bima Nugraha', 'SI-47-01', 124, 'Kota Medan, Sumatera Utara', 1, 'AB'],
            [3, 'Citra Ayuningtyas', 'SI-47-02', 130, 'Kota Semarang, Jawa Tengah', 3, 'A'],
            [4, 'Dimas Prakoso', 'SI-47-02', 126, 'Kota Surabaya, Jawa Timur', 4, 'B'],
            [5, 'Eka Larasati', 'SI-47-03', 121, 'Kota Samarinda, Kalimantan Timur', 2, 'AB'],
            [6, 'Farhan Akbar', 'SI-47-03', 119, 'Kota Makassar, Sulawesi Selatan', 3, 'B'],
            [7, 'Gita Permatasari', 'SI-47-04', 122, 'Kota Denpasar, Bali', 0, 'A'],
            [8, 'Hafiz Ramadhan', 'SI-47-04', 116, 'Kota Pekanbaru, Riau', 1, 'BC'],
            [9, 'Indah Puspita', 'SI-47-05', 127, 'Kota Yogyakarta, DI Yogyakarta', 0, 'A'],
            [10, 'Joko Santoso', 'SI-47-05', 118, 'Kota Malang, Jawa Timur', 4, 'B'],
            [11, 'Kevin Wijaya', 'SI-47-06', 125, 'Kota Serang, Banten', 2, 'AB'],
            [12, 'Laras Sekar', 'SI-47-06', 120, 'Kota Jayapura, Papua', 3, 'B'],
            [13, 'Mira Anggraini', 'SI-47-07', 123, 'Kota Padang, Sumatera Barat', 1, 'AB'],
            [14, 'Naufal Hakim', 'SI-47-07', 131, 'Kota Banda Aceh, Aceh', 2, 'A'],
            [15, 'Olivia Hartono', 'SI-47-08', 122, 'Kota Pontianak, Kalimantan Barat', 0, 'B'],
            [16, 'Putra Mahendra', 'SI-47-08', 117, 'Kota Mataram, NTB', 4, 'BC'],
            [17, 'Qonita Azzahra', 'SI-47-09', 126, 'Kota Palembang, Sumatera Selatan', 3, 'AB'],
            [18, 'Rizal Fauzan', 'SI-47-09', 120, 'Kota Bogor, Jawa Barat', 1, 'B'],
            [19, 'Salsabila Putri', 'SI-47-10', 102, 'Kota Cirebon, Jawa Barat', 0, 'C'],   // SKS < 110 → screening gagal
            [20, 'Taufik Hidayat', 'SI-47-10', 129, 'Kota Manado, Sulawesi Utara', 2, 'A'],
            [21, 'Umi Kalsum', 'SI-47-INT', 124, 'Kota Kupang, NTT', 4, 'AB'],
            [22, 'Vino Saputra', 'SI-47-01', 125, 'Kota Balikpapan, Kalimantan Timur', 3, 'B'],
            [23, 'Wulan Sari', 'SI-47-02', 121, 'Kota Tasikmalaya, Jawa Barat', 1, 'AB'],
            [24, 'Yoga Pratama', 'SI-47-03', 127, 'Kota Bekasi, Jawa Barat', 0, 'A'],
            [25, 'Zahra Amelia', 'SI-47-04', 123, 'Kota Depok, Jawa Barat', 2, 'B'],
            [26, 'Arif Rahman', 'SI-47-05', 118, 'Kota Jambi, Jambi', 3, 'B'],
            [27, 'Bella Oktaviani', 'SI-47-06', 112, 'Kota Bengkulu, Bengkulu', 4, 'BC'],
            [28, 'Candra Kirana', 'SI-47-07', 100, 'Kota Ambon, Maluku', 1, 'C'],   // belum memenuhi SKS minimal
        ];
        foreach ($rows as [$no, $name, $class, $sks, $region, $spec, $kp]) {
            $user = User::create([
                'name' => $name, 'email' => sprintf('mhs%02d@', $no).self::DOMAIN, 'role' => 'mahasiswa', 'password' => $password,
                'nim' => '10322300'.sprintf('%04d', $no), 'class_name' => $class, 'sks_passed' => $sks,
                'passed_courses' => $sks >= 110 ? ['KP'] : [], 'course_grades' => ['KP' => $kp],
                'origin_region' => $region, 'specialization' => self::SPECIALIZATIONS[$spec], 'is_active' => true,
            ]);
            $user->joinPeriod($this->period);
            $this->students[sprintf('s%02d', $no)] = $user;
        }
    }

    private function quotas(): void
    {
        $base = ['period_id' => $this->period->id];
        LoadQuota::create($base + ['scope' => 'global', 'category' => null, 'lecturer_id' => null, 'supervise_min' => 1, 'supervise_max' => 4, 'examine_min' => 1, 'examine_max' => 4]);
        LoadQuota::create($base + ['scope' => 'kategori', 'category' => 'dosen_kelas', 'lecturer_id' => null, 'supervise_min' => 2, 'supervise_max' => 5, 'examine_min' => 1, 'examine_max' => 5]);
        LoadQuota::create($base + ['scope' => 'dosen', 'category' => null, 'lecturer_id' => $this->lecturers['budi']->id, 'supervise_min' => 0, 'supervise_max' => 0, 'examine_min' => 2, 'examine_max' => 6]);
    }

    // ------------------------------------------------------------------ topik dosen

    /** @return array<string, Topic> */
    private function bankTopics(): array
    {
        $rows = [
            // kunci => [pengusul, judul, bidang, kuota, objek, peminatan, status, catatan admin, pengusul bersama]
            'gudang' => ['andi', 'Integrasi data persediaan multi-gudang untuk perencanaan pengadaan', 'Supply Chain', 2, 'PT Sinar Distribusi Nusantara', [0, 3], 'diterima', null, ['fajar']],
            'akademik' => ['rina', 'Dashboard pemantauan kinerja layanan akademik berbasis data terintegrasi', 'Business Intelligence', 1, 'Direktorat Akademik Universitas', [0, 1], 'diterima', null, []],
            'umkm' => ['fajar', 'Platform pelaporan keuangan UMKM yang terhubung dengan sistem kasir', 'Sistem Informasi Keuangan', 2, 'Koperasi UMKM Kota Bandung', [1, 3], 'diterima', null, []],
            'produksi' => ['dewi', 'Perancangan ERP penjadwalan produksi terintegrasi dengan perawatan mesin', 'Enterprise Resource Planning', 2, 'PT Tekstil Majalaya Jaya', [3, 4], 'diterima', null, []],
            'energi' => ['andi', 'Aplikasi pemantauan konsumsi energi gedung kampus berbasis IoT', 'Internet of Things', 2, 'Unit Logistik & Fasilitas Kampus', [4, 0], 'menunggu', null, ['sari']],
            'blockchain' => ['budi', 'Kajian tata kelola blockchain untuk arsip digital', 'Tata Kelola TI', 1, 'Arsip Daerah Kota Bandung', [2], 'ditolak', 'Ruang lingkup terlalu luas untuk satu semester; mohon dipersempit ke satu proses arsip.', []],
        ];
        $topics = [];
        foreach ($rows as $key => [$owner, $title, $field, $quota, $object, $specs, $status, $notes, $co]) {
            $topic = Topic::create([
                'period_id' => $this->period->id, 'source' => 'dosen', 'lecturer_id' => $this->lecturers[$owner]->id,
                'title' => $title, 'field' => $field, 'quota' => $quota, 'object' => $object, 'prodi_codes' => ['SI'],
                'specializations' => array_map(fn ($i) => self::SPECIALIZATIONS[$i], $specs),
                'competencies' => "Analisis kebutuhan dan pemodelan proses bisnis.\nPerancangan basis data dan integrasi sistem.",
                'description' => "{$title}. Tim memetakan proses bisnis eksisting di {$object}, merancang solusi sistem terintegrasi, lalu memverifikasi rancangan bersama problem owner.",
                'status' => $status, 'review_notes' => $notes,
                'reviewed_by' => $status === 'menunggu' ? null : $this->prodiAdmin->id, 'reviewed_at' => $status === 'menunggu' ? null : $this->at(2, 1),
                'created_at' => $this->at(1, 2),
            ]);
            $topic->proposers()->attach($this->lecturers[$owner]->id);
            foreach ($co as $c) {
                $topic->proposers()->attach($this->lecturers[$c]->id, ['invited_by' => $this->lecturers[$owner]->id]);
            }
            $topics[$key] = $topic;
        }

        return $topics;
    }

    // ------------------------------------------------------------------ tim

    /** @param array<string, Topic> $bank */
    private function teams(array $bank, ScreeningService $screening, TopicService $topics, QrService $qr): void
    {
        $s = $this->students;
        $l = $this->lecturers;

        // 1. Tim Nusantara — SELESAI: semua nilai final, peer review lengkap → rekap ber-grade
        $t = $this->team('Tim Nusantara', [$s['s01'], $s['s02'], $s['s03']], 'diterima', $screening, $qr);
        $this->apply($t, $bank['gudang'], 'diterima', $l['andi'], $topics, 'Pengalaman KP di bagian gudang distributor.');
        $this->plot($t, 'penguji', $l['budi']);
        $this->logbooks($t, 10, 10, 0, $l['andi'], $s['s01']);
        foreach (['proposal' => 2, 'laporan_progress' => 1, 'laporan_akhir' => 1, 'materi_presentasi' => 1] as $doc => $rev) {
            $this->document($t, $doc, $s['s01'], $rev, 'berhasil', 'disetujui', 'Sudah sesuai template. Lanjutkan.');
        }
        $this->assessAll($t, ['proposal', 'laporan_progress', 'sidang_pembimbing'], $l['andi'], 'pembimbing', 'final', 1.0);
        $this->assessAll($t, ['sidang_penguji', 'pameran'], $l['budi'], 'penguji', 'final', 0.97);
        $this->peer($t, 'semua', [5, 4, 5, 4, 5, 5]);

        // 2. Tim Cakrawala — SELESAI dengan nilai lebih rendah (variasi grade)
        $t = $this->team('Tim Cakrawala', [$s['s04'], $s['s05'], $s['s06']], 'diterima', $screening, $qr);
        $this->independent($t, 'Sistem informasi manajemen aset fasilitas kampus berbasis QR', 'diterima', $topics);
        $this->plot($t, 'pembimbing', $l['rina']);
        $this->plot($t, 'penguji', $l['fajar']);
        $this->logbooks($t, 9, 9, 0, $l['rina'], $s['s04']);
        foreach (['proposal' => 1, 'laporan_progress' => 2, 'laporan_akhir' => 1, 'materi_presentasi' => 1] as $doc => $rev) {
            $this->document($t, $doc, $s['s04'], $rev, 'berhasil', 'disetujui', null);
        }
        $this->assessAll($t, ['proposal', 'laporan_progress', 'sidang_pembimbing'], $l['rina'], 'pembimbing', 'final', 0.85);
        $this->assessAll($t, ['sidang_penguji', 'pameran'], $l['fajar'], 'penguji', 'final', 0.82);
        $this->peer($t, 'semua', [4, 3, 4, 4, 3, 4]);

        // 3. Tim Harmoni — BERJALAN: proposal & progress final, sidang pembimbing draft; log ada yang menunggu & ditolak
        $t = $this->team('Tim Harmoni', [$s['s07'], $s['s08']], 'diterima', $screening, $qr);
        $this->independent($t, 'Aplikasi pemesanan dan antrean layanan bengkel UMKM', 'diterima', $topics);
        $this->plot($t, 'pembimbing', $l['dewi']);
        $this->plot($t, 'penguji', $l['budi']);
        $this->logbooks($t, 9, 6, 1, $l['dewi'], $s['s07']);
        $this->document($t, 'proposal', $s['s07'], 1, 'berhasil', 'disetujui', null);
        $this->document($t, 'laporan_progress', $s['s08'], 2, 'berhasil', 'revisi', 'Bab 3 perlu diagram konteks dan DFD level 1.');
        $this->document($t, 'laporan_akhir', $s['s07'], 1, 'menunggu', 'dikumpulkan', null);
        $this->assessAll($t, ['proposal', 'laporan_progress'], $l['dewi'], 'pembimbing', 'final', 0.8);
        $this->assessAll($t, ['sidang_pembimbing'], $l['dewi'], 'pembimbing', 'draft', 0.75, half: true);
        $this->peer($t, 'sebagian', [4, 4, 5, 4, 4, 4]);

        // 4. Tim Lentera — topik mandiri pernah DITOLAK, lalu diterima di topik dosen; progress gagal sinkron Drive
        $t = $this->team('Tim Lentera', [$s['s09'], $s['s10'], $s['s11']], 'diterima', $screening, $qr);
        $this->independent($t, 'Aplikasi absensi karyawan berbasis wajah', 'ditolak', $topics, 'Topik sudah banyak dikerjakan dan belum menunjukkan integrasi proses bisnis. Silakan pilih topik dosen.');
        $this->apply($t, $bank['akademik'], 'diterima', $l['rina'], $topics, 'Tertarik pada business intelligence akademik.');
        $this->plot($t, 'penguji', $l['andi']);
        $this->logbooks($t, 6, 5, 0, $l['rina'], $s['s09']);
        $this->document($t, 'proposal', $s['s09'], 1, 'berhasil', 'ditinjau', null);
        $this->document($t, 'laporan_progress', $s['s10'], 1, 'gagal', 'dikumpulkan', null);
        $this->assessAll($t, ['proposal'], $l['rina'], 'pembimbing', 'final', 0.78);

        // 5. Tim Pelangi — diterima, topik mandiri MENUNGGU approval admin, belum diplot (demo plotting)
        $t = $this->team('Tim Pelangi', [$s['s12'], $s['s13']], 'diterima', $screening, $qr);
        $this->independent($t, 'Sistem rekomendasi menu katering sehat berbasis data pesanan', 'menunggu', $topics);

        // 6. Tim Samudera — diterima, melamar topik dosen, MENUNGGU keputusan dosen (demo approval pelamar)
        $t = $this->team('Tim Samudera', [$s['s14'], $s['s15'], $s['s16']], 'diterima', $screening, $qr);
        $this->apply($t, $bank['umkm'], 'menunggu', null, $topics, 'Anggota tim pernah membantu pembukuan UMKM keluarga.');

        // 7. Tim Bahari — registrasi MENUNGGU, satu anggota SKS < 110 (screening gagal)
        $this->team('Tim Bahari', [$s['s17'], $s['s18'], $s['s19']], 'menunggu', $screening, $qr);

        // 8. Tim Gemilang — registrasi MENUNGGU, semua syarat terpenuhi
        $this->team('Tim Gemilang', [$s['s20'], $s['s21']], 'menunggu', $screening, $qr);

        // 9. Tim Rinjani — registrasi DITOLAK (tim dibubarkan, pendaftar tersimpan di log)
        Team::create([
            'period_id' => $this->period->id, 'name' => 'Tim Rinjani', 'leader_id' => null, 'status' => 'ditolak',
            'review_notes' => 'KHS anggota kedua tidak terbaca. Silakan daftar ulang dengan KHS yang jelas.',
            'reviewed_by' => $this->prodiAdmin->id, 'reviewed_at' => $this->at(2, 3), 'submitted_at' => $this->at(2, 1),
            'archived_members' => [
                ['name' => 'Hendra Kurniawan', 'is_leader' => true, 'nim' => '103223009901', 'email' => 'hendra@'.self::DOMAIN, 'sks_passed' => 120, 'grades' => ['KP' => 'B'], 'specialization' => self::SPECIALIZATIONS[1], 'origin_region' => 'Kota Cimahi, Jawa Barat'],
                ['name' => 'Intan Maharani', 'is_leader' => false, 'nim' => '103223009902', 'email' => 'intan@'.self::DOMAIN, 'sks_passed' => 115, 'grades' => ['KP' => 'BC'], 'specialization' => self::SPECIALIZATIONS[1], 'origin_region' => 'Kota Garut, Jawa Barat'],
            ],
        ]);

        // 10. Tim Kirana — DRAFT: ketua sudah mengundang, undangan anggota belum dijawab
        $t = $this->team('Tim Kirana', [$s['s22']], 'draft', $screening, $qr);
        $t->members()->create(['user_id' => $s['s23']->id, 'invite_status' => 'diundang']);
        // s24 … s28 belum bertim (bebas untuk demo registrasi tim baru; s28 belum memenuhi SKS)

        foreach (TeamLecturer::query()->whereIn('team_id', Team::query()->where('period_id', $this->period->id)->pluck('id'))->get() as $assignment) {
            LecturerProfile::grant($assignment->lecturer_id, $assignment->role);
        }
    }

    private function team(string $name, array $members, string $status, ScreeningService $screening, QrService $qr): Team
    {
        $team = Team::create([
            'period_id' => $this->period->id, 'name' => $name, 'leader_id' => $members[0]->id, 'status' => $status,
            'submitted_at' => $status === 'draft' ? null : $this->at(1, 3),
            'reviewed_by' => $status === 'diterima' ? $this->prodiAdmin->id : null,
            'reviewed_at' => $status === 'diterima' ? $this->at(2, 0) : null,
        ]);
        foreach ($members as $i => $user) {
            $team->members()->create([
                'user_id' => $user->id, 'is_leader' => $i === 0, 'invite_status' => 'diterima', 'responded_at' => $this->at(1, 2),
                'khs_path' => $this->pdf("registrations/{$team->id}/{$user->id}/khs.pdf", "KHS {$user->name}"),
                'domicile_path' => $this->pdf("registrations/{$team->id}/{$user->id}/domisili.pdf", "Domisili {$user->name}"),
            ]);
        }
        $screening->apply($team);
        if ($status === 'diterima') {
            $qr->ensureToken($team);
            foreach ($members as $user) {
                $user->notify(new SystemNotice('Registrasi tim diterima', "Registrasi {$name} diterima admin prodi.", route('mahasiswa.dashboard')));
            }
        }

        return $team;
    }

    private function independent(Team $team, string $title, string $status, TopicService $topics, ?string $notes = null): void
    {
        Topic::create([
            'period_id' => $this->period->id, 'source' => 'mandiri', 'team_id' => $team->id, 'title' => $title,
            'description' => "{$title}. Masalah ditemukan saat Kerja Praktek: data antarbagian belum terhubung sehingga perlu solusi sistem terintegrasi.",
            'field' => 'Sistem terintegrasi', 'object' => 'Mitra Kerja Praktek '.$team->name, 'status' => $status, 'review_notes' => $notes,
            'reviewed_by' => $status === 'menunggu' ? null : $this->prodiAdmin->id, 'reviewed_at' => $status === 'menunggu' ? null : $this->at(3, 1),
        ]);
        $topics->refreshFinalTopic($team);
    }

    private function apply(Team $team, Topic $topic, string $status, ?User $lecturer, TopicService $topics, string $motivation): void
    {
        $app = TopicApplication::create(['topic_id' => $topic->id, 'team_id' => $team->id, 'motivation' => $motivation, 'portfolio_url' => 'https://example.com/portofolio-'.Str::slug($team->name)]);
        if ($status === 'diterima') {
            $topics->decide($app, true, 'Selamat bergabung, jadwalkan bimbingan pertama.', $lecturer);
        }
    }

    private function plot(Team $team, string $role, User $lecturer): void
    {
        TeamLecturer::query()->updateOrCreate(['team_id' => $team->id, 'role' => $role], ['lecturer_id' => $lecturer->id, 'source' => 'admin', 'assigned_by' => $this->prodiAdmin->id]);
    }

    /** $approved disetujui, $rejected ditolak (revisi), sisanya menunggu. */
    private function logbooks(Team $team, int $total, int $approved, int $rejected, User $lecturer, User $author): void
    {
        $activities = ['Kick-off dan penyamaan ruang lingkup', 'Analisis proses bisnis eksisting', 'Wawancara problem owner', 'Penyusunan kebutuhan sistem',
            'Perancangan basis data', 'Perancangan antarmuka', 'Review prototipe tahap 1', 'Integrasi modul', 'Pengujian fungsional', 'Persiapan sidang'];
        for ($i = 1; $i <= $total; $i++) {
            $status = $i <= $approved ? 'disetujui' : ($i <= $approved + $rejected ? 'revisi' : 'menunggu');
            $date = $this->at(2, 0)->addDays(($i - 1) * 7 + 1);
            $log = Logbook::create([
                'team_id' => $team->id, 'session_no' => $i, 'session_date' => min($date, now()->subDay())->toDateString(),
                'activity' => "Sesi {$i}: ".$activities[($i - 1) % count($activities)].'.', 'responsible_person' => $author->name,
                'output' => "Notulen dan tindak lanjut sesi {$i}.", 'notes' => 'https://example.com/notulen-'.Str::slug($team->name)."-{$i}",
                'status' => $status, 'feedback' => $status === 'revisi' ? 'Lengkapi bukti notulen dan tanda tangan problem owner.' : null,
                'reviewed_by' => $status === 'menunggu' ? null : $lecturer->id, 'reviewed_at' => $status === 'menunggu' ? null : min($date->copy()->addDay(), now()),
                'created_by' => $author->id,
            ]);
            $log->snapshot('dibuat', $author);
        }
    }

    private function document(Team $team, string $type, User $owner, int $revisions, string $sync, string $review, ?string $feedback): void
    {
        $milestone = Milestone::query()->where('period_id', $this->period->id)->where('document_type', $type)->firstOrFail();
        $document = Document::create([
            'team_id' => $team->id, 'milestone_id' => $milestone->id, 'owner_id' => $owner->id, 'owner_email' => $owner->email,
            'document_type' => $type, 'review_status' => $review, 'feedback' => $feedback,
            'feedback_by' => $feedback ? $team->supervisor?->lecturer_id : null, 'feedback_at' => $feedback ? now()->subDays(2) : null,
        ]);
        $name = DocumentService::fileName($milestone->name, $team->name, 'pdf');
        $uploaded = min($milestone->due_at->copy()->subDays(2), now()->subDays(1));
        for ($r = 1; $r <= $revisions; $r++) {
            $last = $r === $revisions;
            $path = $this->pdf("documents/{$this->period->id}/tim-{$team->id}/{$document->id}/r{$r}/{$name}", "{$milestone->name} {$team->name} revisi {$r}");
            $status = $last ? $sync : 'dilewati';
            $version = $document->versions()->create([
                'revision_no' => $r, 'original_name' => Str::slug($milestone->name)."-v{$r}.pdf", 'stored_name' => $name,
                'mime' => 'application/pdf', 'size' => Storage::disk('local')->size($path), 'local_path' => $path,
                'checksum' => hash('sha256', Storage::disk('local')->get($path)), 'idempotency_key' => (string) Str::uuid(),
                'sync_action' => $r === 1 ? 'create' : 'update', 'sync_status' => $status,
                'sync_attempts' => $status === 'gagal' ? 3 : ($status === 'berhasil' ? 1 : 0),
                'sync_error' => $status === 'gagal' ? 'Folder Google Drive Database untuk milestone '.$milestone->name.' belum diisi.' : ($status === 'dilewati' ? 'Digantikan revisi '.$revisions.'.' : null),
                'synced_at' => $status === 'berhasil' ? $uploaded->copy()->addMinutes(2) : null,
                'uploaded_by' => $owner->id, 'created_at' => $uploaded->copy()->subDays($revisions - $r),
            ]);
            if ($last) {
                $synced = $status === 'berhasil';
                $document->update([
                    'current_revision' => $synced ? $r : 0, 'current_version_id' => $synced ? $version->id : null,
                    'drive_file_id' => $synced ? 'demo-'.Str::random(20) : null,
                ]);
                DriveOperation::create(['document_id' => $document->id, 'document_version_id' => $version->id, 'action' => $r === 1 ? 'create' : 'update',
                    'status' => $synced ? 'berhasil' : ($status === 'gagal' ? 'gagal' : 'berhasil'), 'error' => $version->sync_error, 'created_at' => $uploaded]);
            }
        }
        if ($sync !== 'berhasil') {
            // Belum tersinkron: revisi terbaru tetap dibuka dari server
            $document->update(['current_revision' => $revisions, 'current_version_id' => $document->versions()->first()->id]);
        }
    }

    /**
     * Nilai semua indikator bentuk penilaian. $level = perkiraan capaian (0–1) → interval per indikator; indikator lisan diberi
     * level pemahaman. $half = hanya separuh indikator (draft yang belum lengkap).
     */
    private function assessAll(Team $team, array $components, User $lecturer, string $role, string $status, float $level, bool $half = false): void
    {
        $calculator = app(ScoreCalculator::class);
        $oral = OralRubricLevel::query()->orderBy('level')->get()->keyBy('level');
        foreach ($components as $component) {
            $assessment = Assessment::create([
                'team_id' => $team->id, 'assessor_id' => $lecturer->id, 'component' => $component, 'assessor_role' => $role,
                'status' => $status, 'channel' => 'web', 'submitted_at' => $status === 'final' ? now()->subDays(2) : null,
                'feedback' => $status === 'final' ? 'Secara umum baik. Perjelas keterkaitan solusi dengan proses bisnis mitra.' : null,
            ]);
            $indicators = $calculator->indicatorsFor($component);
            foreach ($indicators as $i => $indicator) {
                if ($half && $i % 2 === 1) {
                    continue;
                }
                $max = $indicator->maxInterval();
                $score = max(1, min($max, (int) round($max * $level + (($i % 3) - 1) * 0.6)));
                $useOral = $indicator->usesOralFactor($component);
                $oralLevel = $useOral ? max(1, min($oral->keys()->max() ?? 5, (int) round(5 * $level) - ($i % 2))) : null;
                $assessment->scores()->create([
                    'indicator_id' => $indicator->id, 'score' => $score, 'oral_level' => $oralLevel,
                    'factor' => $useOral ? (float) ($oral[$oralLevel]->factor ?? 1) : 1,
                ]);
            }
        }
    }

    /** Peer review antaranggota: 'semua' = semua anggota mengisi, 'sebagian' = hanya ketua. $pattern = skor per aspek. */
    private function peer(Team $team, string $mode, array $pattern): void
    {
        $members = $team->members()->where('invite_status', 'diterima')->get();
        $aspects = array_keys(config('capstone.peer_aspects'));
        foreach ($members as $r => $reviewer) {
            if ($mode === 'sebagian' && ! $reviewer->is_leader) {
                continue;
            }
            foreach ($members as $reviewee) {
                if ($reviewee->user_id === $reviewer->user_id) {
                    continue;
                }
                foreach ($aspects as $a => $aspect) {
                    PeerReview::create(['team_id' => $team->id, 'reviewer_id' => $reviewer->user_id, 'reviewee_id' => $reviewee->user_id,
                        'aspect' => $aspect, 'score' => \App\Models\PeerAspect::POINTS[max(1, min(5, $pattern[$a % count($pattern)] - (($r + $a) % 4 === 0 ? 1 : 0)))]]);
                }
            }
        }
    }

    // ------------------------------------------------------------------ lain-lain

    private function supportFiles(): void
    {
        foreach ([
            ['Panduan Capstone Design & Project', 'Panduan', ['dosen', 'mahasiswa'], 'panduan-capstone.pdf'],
            ['Template Proposal Capstone', 'Template', ['mahasiswa'], 'template-proposal.pdf'],
            ['Pedoman Penilaian Sidang untuk Dosen', 'Pedoman', ['dosen'], 'pedoman-penilaian.pdf'],
        ] as [$title, $category, $visible, $file]) {
            $path = $this->pdf("support-files/showcase-{$file}", $title);
            SupportFile::create(['title' => $title, 'category' => $category, 'visible_to' => $visible, 'description' => 'Berkas contoh untuk demo.',
                'path' => $path, 'original_name' => $file, 'mime' => 'application/pdf', 'size' => Storage::disk('local')->size($path), 'uploaded_by' => $this->prodiAdmin->id]);
        }
    }

    /** Riwayat reminder (manual & otomatis): terkirim dan gagal, agar halaman Reminder & Monitoring penilaian berisi data. */
    private function reminders(): void
    {
        $rows = [
            ['dewi', 'manual', 'email', 'dewi@'.self::DOMAIN, 'terkirim', null, 3],
            ['dewi', 'manual', 'whatsapp', '', 'gagal', 'Nomor WhatsApp dosen kosong.', 3],
            ['rina', 'otomatis', 'email', 'rina@'.self::DOMAIN, 'terkirim', null, 1],
            ['budi', 'otomatis', 'email', 'budi@'.self::DOMAIN, 'gagal', 'SMTP: alamat tujuan tidak dapat dihubungi.', 1],
        ];
        foreach ($rows as $i => [$key, $trigger, $channel, $recipient, $status, $error, $days]) {
            ReminderDispatch::create([
                'period_id' => $this->period->id, 'dedupe_key' => 'showcase-'.$i.'-'.Str::random(8), 'job_type' => 'penilaian', 'job_ref' => 'sidang_pembimbing',
                'lecturer_id' => $this->lecturers[$key]->id, 'trigger' => $trigger, 'channel' => $channel, 'recipient' => $recipient,
                'payload' => ['message' => 'Pengingat penilaian Capstone (contoh data demo).'], 'status' => $status, 'attempts' => 1,
                'last_error' => $error, 'deadline_at' => now()->addDays(10), 'sent_at' => $status === 'terkirim' ? now()->subDays($days) : null,
                'created_by' => $trigger === 'manual' ? $this->prodiAdmin->id : null, 'created_at' => now()->subDays($days),
            ]);
        }
    }

    /** Tanggal pada minggu ke-$week hari ke-$day periode. */
    private function at(int $week, int $day): Carbon
    {
        return $this->start->copy()->addWeeks($week - 1)->addDays($day)->setTime(10, 0);
    }

    /** PDF satu halaman minimal agar tombol Lihat / Unduh dapat dibuka. */
    private function pdf(string $path, string $text): string
    {
        $text = preg_replace('/[^A-Za-z0-9 .,\-]/', '', $text);
        $stream = "BT /F1 18 Tf 72 720 Td ({$text}) Tj ET BT /F1 11 Tf 72 696 Td (Dokumen contoh untuk demo aplikasi Capstone.) Tj ET";
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
