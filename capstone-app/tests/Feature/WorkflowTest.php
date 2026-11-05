<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\ForbiddenKeyword;
use App\Models\Logbook;
use App\Models\PeerReview;
use App\Models\Period;
use App\Models\ReminderDispatch;
use App\Models\Team;
use App\Models\TeamLecturer;
use App\Models\Topic;
use App\Models\TopicApplication;
use App\Models\User;
use App\Services\Drive\DriveGateway;
use App\Services\LoadBalanceService;
use App\Services\ScoreCalculator;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        // Admin bekerja di dalam Dashboard Periode (periode aktif demo)
        $this->withSession([\App\Models\Period::WORKSPACE_KEY => \App\Models\Period::active()->id]);
        $this->withoutVite();
        Storage::fake('local');
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    public function test_full_registration_and_topic_flow(): void
    {
        $member = User::query()->where('nim', '102022300013')->first(); // s13 — SKS kurang
        $admin = User::query()->where('role', 'admin')->first();

        $other = $this->user('mahasiswa10@gmail.com');
        $other->memberships()->delete();
        $khs = fn () => UploadedFile::fake()->create('khs.pdf', 100, 'application/pdf');
        $course = Period::active()->registrationRule->courses()->firstOrFail();
        $person = fn (User $u, string $region) => [
            'nim' => $u->nim, 'name' => $u->name, 'email' => $u->email, 'specialization' => '', 'sks_passed' => $u->sks_passed,
            'grades' => [$course->id => 'B'], 'origin_region' => $region, 'khs' => $khs(),
        ];
        // Ketua membuat akun sekaligus mendaftarkan tim di halaman registrasi publik (F21)
        $form = fn (User $mate) => [
            'name' => 'Tim Uji',
            'leader' => [
                'nim' => '1030100', 'name' => 'Ketua Uji', 'email' => 'ketua.uji@student.test', 'specialization' => 'Enterprise Resource Planning',
                'sks_passed' => 125, 'grades' => [$course->id => 'A'], 'origin_region' => 'Bandung, Jawa Barat', 'khs' => $khs(),
                'password' => 'rahasia123', 'password_confirmation' => 'rahasia123',
            ],
            'members' => [$person($mate, 'Medan, Sumatera Utara')],
        ];

        // F21 — anggota yang tidak memenuhi prasyarat (SKS kurang) ditolak sistem
        $this->post(route('register.store'), $form($member))->assertSessionHasErrors('members.0.sks_passed');
        $this->assertFalse(User::query()->where('nim', '1030100')->exists());

        // KHS anggota wajib
        $incomplete = $form($other);
        unset($incomplete['members'][0]['khs']);
        $this->post(route('register.store'), $incomplete)->assertSessionHasErrors('members.0.khs');

        // Registrasi lengkap langsung diajukan ke admin
        $this->post(route('register.store'), $form($other))->assertSessionHasNoErrors();
        $leader = User::query()->where('nim', '1030100')->sole();
        $team = $leader->teamFor(Period::active());
        $this->assertSame('menunggu', $team->status);
        $this->assertSame($team->id, $other->teamFor(Period::active())?->id);
        $this->assertSame('Medan, Sumatera Utara', $other->fresh()->origin_region);
        $this->assertSame([$course->code => 'B'], $other->fresh()->course_grades);
        $this->assertSame('Enterprise Data Management', $other->fresh()->specialization, 'Peminatan kosong tidak menimpa data admin');
        $this->assertSame(100, $team->screening_score);

        // Sesama anggota dapat melihat KHS anggota lain
        $otherMember = $team->members()->where('user_id', $other->id)->first();
        $this->actingAs($leader)->get(route('attachments.download', [$team, $otherMember, 'khs']))->assertOk();

        // F6 — penolakan wajib catatan, lalu diterima
        $this->actingAs($admin)->post(route('admin.teams.decide', $team), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('diterima', $team->fresh()->status);
        $this->assertNotNull($team->activeQrToken(), 'QR dibuat saat tim diterima (F30)');

        // F23 + F7 — topik mandiri dengan kata kunci terlarang ditandai
        ForbiddenKeyword::create(['keyword' => 'sistem informasi akademik']);
        $this->actingAs($leader)->post(route('mahasiswa.topic.store'), [
            'title' => 'Sistem Informasi Akademik terpadu',
            'description' => str_repeat('Permasalahan integrasi data antar unit. ', 3),
            'object' => 'PT Contoh Logistik',
        ])->assertSessionHasNoErrors();
        $topic = $team->fresh()->independentTopic;
        $this->assertSame(['sistem informasi akademik'], $topic->keyword_hits);
        $this->assertSame('PT Contoh Logistik', $topic->object);
        $portfolio = ['portfolio_url' => 'https://drive.google.com/drive/folders/portofolio-tim'];

        // Topik mandiri & topik dosen paralel: boleh melamar topik dosen sebelum topik mandiri diputuskan admin
        $bankTopic = Topic::query()->published()->where('title', 'like', 'Dashboard%')->first();
        $this->actingAs($leader)->post(route('mahasiswa.bank.apply', $bankTopic), $portfolio)->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.topics.decide', $topic), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame($topic->id, $team->fresh()->final_topic_id);

        // F22 + F43 — dosen menerima lamaran → dosen otomatis pembimbing & topik final tergantikan (F8)
        $application = TopicApplication::query()->where('team_id', $team->id)->first();
        $this->assertSame($portfolio['portfolio_url'], $application->portfolio_url);
        $this->actingAs($this->user('dosen2@gmail.com'))->post(route('dosen.applications.decide', $application), ['decision' => 'approve'])->assertSessionHasNoErrors();

        $team->refresh();
        $this->assertSame($bankTopic->id, $team->final_topic_id);
        $this->assertSame($bankTopic->lecturer_id, $team->supervisor->lecturer_id);
        $this->assertSame('sistem', $team->supervisor->source);
        $this->assertSame(0, $bankTopic->fresh()->remainingQuota());
    }

    public function test_lecturer_topic_competency_must_match_period_specializations(): void
    {
        $lecturer = $this->user('dosen@gmail.com');
        $form = [
            'title' => 'Optimasi rute distribusi bahan baku', 'field' => 'Supply Chain', 'quota' => 1,
            'description' => str_repeat('Permasalahan distribusi lintas gudang yang belum terintegrasi. ', 2),
            'object' => 'PT Contoh Distribusi', 'prodi_codes' => ['TI', 'SI'],
            'competencies' => "Pemodelan jaringan distribusi.\nAnalisis data rute.",
        ];
        $coProposer = $this->user('dosen2@gmail.com');

        // Peminatan di luar daftar aturan registrasi periode ditolak; prodi wajib minimal satu
        $this->actingAs($lecturer)->post(route('dosen.topics.store'), $form + ['specializations' => ['Peminatan Fiktif']])->assertSessionHasErrors('specializations.0');
        $this->actingAs($lecturer)->post(route('dosen.topics.store'), ['prodi_codes' => []] + $form + ['specializations' => ['Enterprise Infrastructure Management']])->assertSessionHasErrors('prodi_codes');
        $this->actingAs($lecturer)->post(route('dosen.topics.store'), $form)->assertSessionHasErrors('specializations');

        $form['specializations'] = ['Enterprise Infrastructure Management', 'Enterprise Resource Planning'];
        $this->actingAs($lecturer)->post(route('dosen.topics.store'), $form + ['proposer_ids' => [$coProposer->id]])->assertSessionHasNoErrors();
        $topic = Topic::query()->where('title', $form['title'])->firstOrFail();
        $this->assertSame(['Enterprise Infrastructure Management', 'Enterprise Resource Planning'], $topic->specializations);
        $this->assertSame($form['competencies'], $topic->competencies);
        $this->assertSame(['TI', 'SI'], $topic->prodi_codes);
        $this->assertSame('PT Contoh Distribusi', $topic->object);

        // Pembuat = pengusul utama; dosen yang diundang menjadi pengusul bersama dan mendapat notifikasi
        $this->assertSame([$lecturer->id, $coProposer->id], $topic->proposers->pluck('id')->all());
        $this->assertSame($lecturer->id, $topic->lecturer_id);
        $notice = $coProposer->notifications()->latest()->first();
        $this->assertSame("Anda diinvite oleh {$lecturer->name} untuk menjadi pengusul pada topik dosen “{$topic->title}”.", $notice->data['body']);

        // Pengusul bersama berakses penuh: melihat dan mengubah topik; pengusul utama tetap tercatat
        $this->actingAs($coProposer)->get(route('dosen.topics.index'))->assertOk()->assertSee($topic->title)->assertSee('Anda pengusul bersama');
        $this->actingAs($coProposer)->put(route('dosen.topics.update', $topic), ['quota' => 2] + $form)->assertSessionHasNoErrors();
        $this->assertSame(2, $topic->fresh()->quota);
        $this->assertSame([$lecturer->id, $coProposer->id], $topic->fresh()->proposers->pluck('id')->all());

        // Dosen yang tidak diundang tidak berakses
        $this->actingAs($this->user('dosen3@gmail.com'))->put(route('dosen.topics.update', $topic), $form)->assertForbidden();

        // Pengusul utama menghapus undangan; pengusul utama tetap tercatat
        $this->actingAs($lecturer)->put(route('dosen.topics.update', $topic), $form)->assertSessionHasNoErrors();
        $this->assertSame([$lecturer->id], $topic->fresh()->proposers->pluck('id')->all());

        // Pengusul bersama juga dapat menghapus topik
        $topic->proposers()->attach($coProposer->id, ['invited_by' => $lecturer->id]);
        $this->actingAs($coProposer)->delete(route('dosen.topics.destroy', $topic))->assertSessionHasNoErrors();
        $this->assertModelMissing($topic);
    }

    public function test_applicants_of_shared_topic_reach_every_proposer(): void
    {
        // Topik "Integrasi data persediaan…" diusulkan dsn1 (utama) bersama dsn4
        $primary = $this->user('dosen@gmail.com');
        $coProposer = $this->user('dosen4@gmail.com');
        $topic = Topic::query()->published()->where('title', 'like', 'Integrasi data persediaan%')->firstOrFail();
        $this->assertSame([$primary->id, $coProposer->id], $topic->proposers->pluck('id')->all());

        $student = $this->user('mahasiswa4@gmail.com');
        $team = Team::query()->where('name', 'Tim Lintang')->first();
        $this->actingAs($student)->post(route('mahasiswa.bank.apply', $topic), ['portfolio_url' => 'https://drive.google.com/portofolio'])->assertSessionHasNoErrors();

        // Notifikasi & daftar approval sampai ke seluruh pengusul
        foreach ([$primary, $coProposer] as $lecturer) {
            $this->assertSame('Lamaran topik baru', $lecturer->notifications()->latest()->first()->data['title']);
            // Halaman utama: pilih topik ("Lihat Pengajuan") → halaman pengajuan topik itu
            $this->actingAs($lecturer)->get(route('dosen.applications.index'))->assertOk()->assertSee($topic->title)
                ->assertSee('Lihat Pengajuan')->assertSee(route('dosen.applications.show', $topic));
            $this->actingAs($lecturer)->get(route('dosen.applications.show', $topic))->assertOk()->assertSee($team->name)->assertSee('Riwayat');
        }

        // Detail tim (waktu pengajuan, SKS, nilai mata kuliah, KHS) dan filter peminatan anggota tim
        $application = TopicApplication::query()->where('team_id', $team->id)->where('topic_id', $topic->id)->firstOrFail();
        $spec = $student->specialization;
        $this->assertNotNull($spec);
        $this->actingAs($this->user('dosen5@gmail.com'))->get(route('dosen.applications.show', $topic))->assertForbidden();
        $this->actingAs($coProposer)->get(route('dosen.applications.show', $topic))
            ->assertSee('Detail tim')->assertSee('Waktu pengajuan')->assertSee($application->created_at->translatedFormat('j M Y, H:i'))
            ->assertSee('Jumlah SKS lulus')->assertSee('Lihat KHS');
        $this->actingAs($coProposer)->get(route('dosen.applications.show', [$topic, 'peminatan' => $spec]))->assertSee($team->name);
        $this->actingAs($coProposer)->get(route('dosen.applications.show', [$topic, 'peminatan' => 'Peminatan Fiktif']))
            ->assertDontSee('Detail '.$team->name)->assertSee('Tidak ada pengajuan dari tim berpeminatan Peminatan Fiktif.');
        $khsMember = $team->members()->whereNotNull('khs_path')->first();
        if ($khsMember) {
            \Illuminate\Support\Facades\Storage::disk('local')->put($khsMember->khs_path, 'pdf');
            $this->actingAs($coProposer)->get(route('attachments.download', [$team, $khsMember, 'khs']))->assertOk();
            $this->actingAs($this->user('dosen5@gmail.com'))->get(route('attachments.download', [$team, $khsMember, 'khs']))->assertForbidden();
        }

        // Pengusul bersama memutuskan → dia yang menjadi pembimbing
        $this->actingAs($this->user('dosen3@gmail.com'))->post(route('dosen.applications.decide', $application), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($coProposer)->post(route('dosen.applications.decide', $application), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame($coProposer->id, $team->fresh()->supervisor->lecturer_id);
    }

    public function test_supervisor_approves_selected_logbooks_at_once(): void
    {
        $lecturer = $this->user('dosen@gmail.com');
        $team = Team::query()->whereHas('supervisor', fn ($q) => $q->where('lecturer_id', $lecturer->id))->firstOrFail();
        $student = $team->activeUsers()->first();
        $logs = collect([31, 32, 33])->map(fn ($no) => Logbook::query()->create([
            'team_id' => $team->id, 'created_by' => $student->id, 'session_no' => $no, 'session_date' => now()->subDays(40 - $no),
            'activity' => "Bimbingan {$no}", 'status' => 'menunggu',
        ]));
        // Log tim lain (bukan bimbingan dosen ini) ikut dikirim → dilewati
        $foreign = Logbook::query()->where('status', 'menunggu')->whereDoesntHave('team.supervisor', fn ($q) => $q->where('lecturer_id', $lecturer->id))->first();

        $this->actingAs($lecturer)->get(route('dosen.logbooks.index'))->assertOk()->assertSee('Setujui pilihan')->assertSee('Pilih semua log yang tampil');
        $this->actingAs($lecturer)->post(route('dosen.logbooks.approve-many'), [])->assertSessionHasErrors('logbooks');

        $ids = $logs->take(2)->pluck('id')->push($foreign?->id)->filter()->values()->all();
        $this->actingAs($lecturer)->post(route('dosen.logbooks.approve-many'), ['logbooks' => $ids])->assertSessionHasNoErrors()
            ->assertSessionHas('status', '2 log bimbingan disetujui.');

        $this->assertSame(['disetujui', 'disetujui', 'menunggu'], $logs->map(fn ($l) => $l->fresh()->status)->all());
        if ($foreign) {
            $this->assertSame('menunggu', $foreign->fresh()->status);
        }
        // Satu notifikasi per tim untuk seluruh log yang disetujui
        $this->assertSame(1, $student->notifications()->get()->filter(fn ($n) => $n->data['title'] === 'Log sesi 31, 32 disetujui')->count());
    }

    public function test_admin_manages_lecturer_assessment_schedule_with_two_assessors(): void
    {
        $admin = $this->user('admin@gmail.com');
        $team = Team::query()->where('name', 'Tim Arunika')->firstOrFail();
        $period = $team->period;

        // Bentuk penilaian & penilai wajib; jadwal opsional tetapi harus berpasangan
        $this->actingAs($admin)->post(route('admin.periods.components.store', $period), ['label' => 'Seminar Kemajuan', 'roles' => []])->assertSessionHasErrors('roles');
        $this->actingAs($admin)->post(route('admin.periods.components.store', $period), ['label' => 'Seminar Kemajuan', 'roles' => ['penguji'], 'opens_at' => now()->toDateTimeString()])->assertSessionHasErrors('due_at');
        $this->actingAs($admin)->post(route('admin.periods.components.store', $period), ['label' => 'Seminar Kemajuan', 'roles' => ['penguji', 'pembimbing']])->assertSessionHasNoErrors();

        $component = \App\Models\AssessmentComponent::query()->where('label', 'Seminar Kemajuan')->firstOrFail();
        $this->assertSame('seminar_kemajuan', $component->key);
        $this->assertSame(['pembimbing', 'penguji'], $component->roles);
        $this->assertFalse($period->assessmentWindows()->where('component', 'seminar_kemajuan')->exists());
        $this->actingAs($admin)->get(route('admin.periods.edit', $period))->assertOk()->assertSee('Seminar Kemajuan')->assertSee('Pembimbing &amp; Penguji', false)->assertSee('Belum diatur');

        // Jadwal diatur nanti lewat Ubah
        $this->actingAs($admin)->put(route('admin.periods.components.update', [$period, $component]), [
            'label' => 'Seminar Kemajuan', 'roles' => ['pembimbing', 'penguji'], 'opens_at' => now()->subDay()->toDateTimeString(), 'due_at' => now()->addWeek()->toDateTimeString(),
        ])->assertSessionHasNoErrors();
        $this->assertTrue($period->assessmentWindows()->where('component', 'seminar_kemajuan')->exists());

        // Pembimbing dan penguji sama-sama berwenang menilai; masing-masing muncul di daftar penilaian
        $indicator = \App\Models\Indicator::query()->firstOrFail();
        DB::table('indicator_component_weights')->insert(['indicator_id' => $indicator->id, 'component' => 'seminar_kemajuan', 'weight' => 10]);
        $this->actingAs($team->supervisor->lecturer)->get(route('dosen.assessments.edit', [$team, 'seminar_kemajuan']))->assertOk();
        $this->actingAs($team->examiner->lecturer)->get(route('dosen.assessments.edit', [$team, 'seminar_kemajuan']))->assertOk();
        $rows = app(\App\Services\ReminderService::class)->incompleteAssessments($period)->where('component', 'seminar_kemajuan')->where('team.id', $team->id);
        $this->assertSame(['pembimbing', 'penguji'], $rows->pluck('role')->sort()->values()->all());

        // Nilai keduanya wajib final, poinnya dirata-rata: skor 5 & 3 dari bobot 10 → (10 + 6) / 2 = 8
        $calculator = app(ScoreCalculator::class);
        foreach (['pembimbing' => [$team->supervisor->lecturer_id, 5], 'penguji' => [$team->examiner->lecturer_id, 3]] as $role => [$assessor, $score]) {
            $summary = $calculator->componentSummary($team->fresh())->firstWhere('component', 'seminar_kemajuan');
            $this->assertNotSame('final', $summary['status']);
            Assessment::create(['team_id' => $team->id, 'assessor_id' => $assessor, 'component' => 'seminar_kemajuan', 'assessor_role' => $role, 'status' => 'final', 'submitted_at' => now()])
                ->scores()->create(['indicator_id' => $indicator->id, 'score' => $score, 'factor' => 1]);
        }
        $summary = (new ScoreCalculator)->componentSummary($team->fresh())->firstWhere('component', 'seminar_kemajuan');
        $this->assertSame('final', $summary['status']);
        $this->assertEqualsWithDelta(8.0, $summary['points'], 0.01);

        // Sudah ada nilai → tidak dapat dihapus
        $this->actingAs($admin)->delete(route('admin.periods.components.destroy', [$period, $component]))->assertSessionHasErrors('component');
        Assessment::query()->where('component', 'seminar_kemajuan')->delete();
        $this->actingAs($admin)->delete(route('admin.periods.components.destroy', [$period, $component]))->assertSessionHasNoErrors();
        $this->assertModelMissing($component);
        $this->assertFalse(DB::table('indicator_component_weights')->where('component', 'seminar_kemajuan')->exists());
        $this->assertArrayNotHasKey('seminar_kemajuan', config('capstone.components'));
    }

    public function test_admin_approves_selected_team_registrations_at_once(): void
    {
        $admin = $this->user('admin@gmail.com');
        $pending = Team::query()->where('name', 'Tim Samudra')->firstOrFail();
        $accepted = Team::query()->where('name', 'Tim Arunika')->firstOrFail();
        $this->assertSame('menunggu', $pending->status);
        $session = [Period::WORKSPACE_KEY => $pending->period_id];

        // Rincian screening tampil langsung di tabel; waktu diajukan hanya di detail
        $check = collect($pending->screening)->first();
        $this->actingAs($admin)->withSession($session)->get(route('admin.teams.index'))->assertOk()
            ->assertSee('Setujui pilihan')->assertSee('Pilih semua tim yang menunggu')->assertSee($check['label'])->assertDontSee('<th>Diajukan</th>', false);

        $this->actingAs($admin)->withSession($session)->post(route('admin.teams.approve-many'), [])->assertSessionHasErrors('teams');
        // Tim yang sudah diputuskan ikut terkirim → dilewati
        $this->actingAs($admin)->withSession($session)->post(route('admin.teams.approve-many'), ['teams' => [$pending->id, $accepted->id]])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_starts_with($s, '1 registrasi tim diterima.'));
        $this->assertSame('diterima', $pending->fresh()->status);
        $this->assertNotNull($pending->fresh()->activeQrToken());
    }

    public function test_admin_rejects_selected_team_registrations_at_once(): void
    {
        $admin = $this->user('admin@gmail.com');
        $pending = Team::query()->where('name', 'Tim Samudra')->firstOrFail();
        $accepted = Team::query()->where('name', 'Tim Arunika')->firstOrFail();
        $session = [Period::WORKSPACE_KEY => $pending->period_id];

        $this->actingAs($admin)->withSession($session)->get(route('admin.teams.index'))->assertOk()->assertSee('Tolak pilihan');

        // Cari & filter syarat menyaring di browser: baris membawa teks cari dan status syaratnya; riwayat kartu terpisah
        $failed = collect($pending->screening ?? [])->contains('passed', false);
        $this->actingAs($admin)->withSession($session)->get(route('admin.teams.index'))->assertOk()
            ->assertSee('data-requirement="'.($failed ? 'belum' : 'terpenuhi').'"', false)
            ->assertSee('data-search="'.mb_strtolower($pending->name), false)->assertSee('riwayat-judul', false);

        $this->actingAs($admin)->withSession($session)->post(route('admin.teams.reject-many'), [])->assertSessionHasErrors('teams');
        // Tim yang sudah diterima ikut terkirim → dilewati, tetap diterima
        $this->actingAs($admin)->withSession($session)->post(route('admin.teams.reject-many'), ['teams' => [$pending->id, $accepted->id], 'notes' => 'KHS buram'])
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_starts_with($s, '1 registrasi tim ditolak.'));
        $this->assertSame('ditolak', $pending->fresh()->status);
        $this->assertSame('KHS buram', $pending->fresh()->review_notes);
        $this->assertSame('diterima', $accepted->fresh()->status);
    }

    public function test_admin_manages_optional_topic_instructions_shown_on_topic_pages(): void
    {
        $admin = $this->user('admin@gmail.com');
        $session = [Period::WORKSPACE_KEY => Period::active()->id];

        // Kartu instruksi di atas kata kunci terlarang; tanpa instruksi tombol Hapus nonaktif & halaman topik tanpa instruksi
        $this->actingAs($admin)->withSession($session)->get(route('admin.topics.index'))->assertOk()
            ->assertSeeInOrder(['Instruksi topik mandiri', 'Kata kunci terlarang topik mandiri']);
        $this->actingAs($this->user('mahasiswa@gmail.com'))->get(route('mahasiswa.topic.index'))->assertOk()->assertDontSee('aria-label="Instruksi"', false);

        $this->actingAs($admin)->withSession($session)->put(route('admin.topic-instructions.update', 'mandiri'), ['body' => 'Topik mandiri wajib memiliki mitra.'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->withSession($session)->put(route('admin.topic-instructions.update', 'dosen'), ['body' => 'Cantumkan kompetensi yang dibutuhkan.'])->assertSessionHasNoErrors();

        // Instruksi mandiri di tab Topik Tim mahasiswa; instruksi dosen di halaman Topik Dosen milik dosen
        $this->actingAs($this->user('mahasiswa@gmail.com'))->get(route('mahasiswa.topic.index'))->assertOk()
            ->assertSee('Topik mandiri wajib memiliki mitra.')->assertDontSee('Cantumkan kompetensi yang dibutuhkan.');
        $this->actingAs($this->user('dosen@gmail.com'))->get(route('dosen.topics.index'))->assertOk()
            ->assertSee('Cantumkan kompetensi yang dibutuhkan.')->assertDontSee('Topik mandiri wajib memiliki mitra.');

        // Isian kosong / Hapus = tanpa instruksi
        $this->actingAs($admin)->withSession($session)->put(route('admin.topic-instructions.update', 'mandiri'), ['body' => '  '])->assertSessionHasNoErrors();
        $this->actingAs($admin)->withSession($session)->delete(route('admin.topic-instructions.destroy', 'dosen'))->assertSessionHasNoErrors();
        $this->assertSame(0, \App\Models\TopicInstruction::query()->count());
    }

    public function test_admin_topic_approval_tabs_bulk_approve_and_bank_crud(): void
    {
        $admin = $this->user('admin@gmail.com');
        $period = Period::active();
        $session = [Period::WORKSPACE_KEY => $period->id];
        $independent = Topic::query()->where('source', 'mandiri')->where('status', 'menunggu')->firstOrFail();
        $lecturerTopic = Topic::query()->where('source', 'dosen')->where('status', 'menunggu')->firstOrFail();

        // Tab mandiri: menunggu approval + riwayat; tab dosen: menunggu approval + bank topik dosen
        $this->actingAs($admin)->withSession($session)->get(route('admin.topics.index'))->assertOk()
            ->assertSee('Kata kunci terlarang topik mandiri')->assertSee('Approval Topik Mandiri')->assertSee($independent->title)->assertSee('Riwayat')->assertDontSee('Bank Topik Dosen');
        $this->actingAs($admin)->withSession($session)->get(route('admin.topics.index', ['tab' => 'dosen']))->assertOk()
            ->assertSee($lecturerTopic->title)->assertSee('Bank Topik Dosen')->assertSee('Setujui pilihan')->assertSee('Kata kunci terlarang topik dosen');

        // Kata kunci terlarang dipisah per jenis topik: kata kunci topik dosen tidak menandai topik mandiri
        $this->actingAs($admin)->withSession($session)->post(route('admin.keywords.store'), ['source' => 'dosen', 'keyword' => 'Blockchain'])->assertSessionHasNoErrors();
        $service = app(\App\Services\TopicService::class);
        $this->assertSame(['blockchain'], $service->keywordHits('Sistem blockchain logistik', 'dosen'));
        $this->assertSame([], $service->keywordHits('Sistem blockchain logistik', 'mandiri'));

        // Kata kunci baru langsung menandai topik dosen yang sudah menunggu approval; dihapus → tanda hilang
        $word = \Illuminate\Support\Str::lower(\Illuminate\Support\Str::before($lecturerTopic->title, ' '));
        $this->actingAs($admin)->withSession($session)->post(route('admin.keywords.store'), ['source' => 'dosen', 'keyword' => $word])->assertSessionHasNoErrors();
        $this->assertSame([$word], $lecturerTopic->fresh()->keyword_hits);
        $this->assertSame([], $independent->fresh()->keyword_hits ?? []);
        $this->actingAs($admin)->withSession($session)->get(route('admin.topics.index', ['tab' => 'dosen']))->assertSee('Kata kunci terlarang:')
            ->assertSee('Tolak pilihan')->assertSee('Tolak semua flag');
        $this->actingAs($admin)->delete(route('admin.keywords.destroy', ForbiddenKeyword::query()->where('source', 'dosen')->where('keyword', $word)->first()));
        $this->assertSame([], $lecturerTopic->fresh()->keyword_hits);

        // Setujui pilihan → topik dosen masuk bank topik
        $this->actingAs($admin)->withSession($session)->post(route('admin.topics.approve-many'), ['topics' => [$lecturerTopic->id, $independent->id]])
            ->assertSessionHasNoErrors()->assertSessionHas('status', '2 topik disetujui.');
        $this->assertSame('diterima', $lecturerTopic->fresh()->status);
        $this->assertSame('diterima', $independent->fresh()->status);

        // Tolak pilihan: alasan wajib; Tolak semua flag: hanya topik menunggu yang ber-flag, alasan menyebut kata kuncinya
        $flaggedTopic = Topic::create(['period_id' => $period->id, 'source' => 'dosen', 'lecturer_id' => $this->user('dosen2@gmail.com')->id,
            'title' => 'Topik ber-flag', 'description' => 'Deskripsi', 'quota' => 1, 'status' => 'menunggu', 'keyword_hits' => ['terlarang']]);
        $plainTopic = Topic::create(['period_id' => $period->id, 'source' => 'dosen', 'lecturer_id' => $this->user('dosen2@gmail.com')->id,
            'title' => 'Topik biasa', 'description' => 'Deskripsi', 'quota' => 1, 'status' => 'menunggu']);
        $this->actingAs($admin)->withSession($session)->post(route('admin.topics.reject-flagged'), ['source' => 'dosen'])->assertSessionHasNoErrors();
        $this->assertSame('ditolak', $flaggedTopic->fresh()->status);
        $this->assertSame('Topik mengandung kata kunci terlarang: terlarang.', $flaggedTopic->fresh()->review_notes);
        $this->assertSame('menunggu', $plainTopic->fresh()->status);
        $this->actingAs($admin)->withSession($session)->post(route('admin.topics.reject-many'), ['topics' => [$plainTopic->id]])->assertSessionHasErrors('notes');
        $this->actingAs($admin)->withSession($session)->post(route('admin.topics.reject-many'), ['topics' => [$plainTopic->id], 'notes' => 'Belum sesuai.'])->assertSessionHasNoErrors();
        $this->assertSame('ditolak', $plainTopic->fresh()->status);
        $this->assertSame('Belum sesuai.', $plainTopic->fresh()->review_notes);

        // Admin menambah topik ke bank (langsung terbit) atas nama dosen; pengusul diberi notifikasi
        $primary = $this->user('dosen5@gmail.com');
        $co = $this->user('dosen4@gmail.com');
        $form = [
            'title' => 'Topik tambahan admin', 'description' => 'Permasalahan integrasi data.', 'competencies' => 'Analisis data.',
            'prodi_codes' => ['SI'], 'specializations' => ['Enterprise Intelligence System Development'], 'object' => 'PT Admin', 'quota' => 2,
            'lecturer_id' => $primary->id, 'proposer_ids' => [$co->id],
        ];
        $this->actingAs($admin)->withSession($session)->post(route('admin.lecturer-topics.store'), ['lecturer_id' => ''] + $form)->assertSessionHasErrors('lecturer_id');
        $this->actingAs($admin)->withSession($session)->post(route('admin.lecturer-topics.store'), $form)->assertSessionHasNoErrors();
        $topic = Topic::query()->where('title', 'Topik tambahan admin')->firstOrFail();
        $this->assertSame('diterima', $topic->status);
        $this->assertSame([$primary->id, $co->id], $topic->proposers->pluck('id')->all());
        $this->assertSame('Topik ditambahkan admin', $primary->notifications()->get()->firstWhere('data.title', 'Topik ditambahkan admin')?->data['title']);

        // Ubah: tetap terbit; hapus bila belum ada pelamar
        $this->actingAs($admin)->withSession($session)->put(route('admin.lecturer-topics.update', $topic), ['quota' => 3] + $form)->assertSessionHasNoErrors();
        $this->assertSame(3, $topic->fresh()->quota);
        $this->assertSame('diterima', $topic->fresh()->status);
        $this->actingAs($admin)->withSession($session)->delete(route('admin.lecturer-topics.destroy', $topic))->assertSessionHasNoErrors();
        $this->assertModelMissing($topic);

        // Topik yang sudah diterima tim tetap dapat dihapus admin: tim kehilangan topik dosen & pembimbing otomatisnya, lalu diberi notifikasi
        $team = Team::query()->where('name', 'Tim Arunika')->firstOrFail();
        $taken = Topic::query()->findOrFail($team->final_topic_id);
        $this->assertSame('dosen', $taken->source);
        $student = $team->activeUsers()->first();
        $this->actingAs($admin)->withSession($session)->delete(route('admin.lecturer-topics.destroy', $taken))->assertSessionHasNoErrors();
        $this->assertModelMissing($taken);
        $team->refresh();
        $this->assertNotSame($taken->id, $team->final_topic_id);
        $this->assertFalse($team->applications()->where('status', 'diterima')->exists());
        $this->assertNull($team->supervisor()->where('source', 'sistem')->first());
        $notice = $student->notifications()->get()->firstWhere('data.title', 'Topik dosen dihapus admin');
        $this->assertStringContainsString('silakan ajukan topik lain', $notice->data['body']);
    }

    public function test_admin_progress_monitoring_searches_and_filters_accepted_teams(): void
    {
        $admin = $this->user('admin@gmail.com');
        $names = fn ($response) => $response->viewData('teams')->getCollection()->pluck('name')->all();

        $all = $this->actingAs($admin)->get(route('admin.monitoring.progress'))->assertOk()
            ->assertSeeText('Topik dosen')->assertSeeText('Topik mandiri')->assertSeeText('Detail tim');
        $this->assertNotContains('Tim Samudra', $names($all));

        $byName = $this->get(route('admin.monitoring.progress', ['q' => 'lintang']));
        $this->assertSame(['Tim Lintang'], $names($byName));

        $lintang = Team::query()->where('name', 'Tim Lintang')->with('members.user')->first();
        $member = $lintang->members->first()->user;
        $this->assertContains('Tim Lintang', $names($this->get(route('admin.monitoring.progress', ['q' => $member->name]))));
        $this->assertContains('Tim Lintang', $names($this->get(route('admin.monitoring.progress', ['q' => 'penjadwalan perawatan']))));

        $byPeminatan = $this->get(route('admin.monitoring.progress', ['peminatan' => $member->specialization]));
        $this->assertContains('Tim Lintang', $names($byPeminatan));
        foreach ($byPeminatan->viewData('teams') as $team) {
            $this->assertTrue($team->members->contains(fn ($m) => $m->user->specialization === $member->specialization));
        }
    }

    public function test_admin_manages_lecturer_quotas_for_many_lecturers_at_once(): void
    {
        $admin = $this->user('admin@gmail.com');
        $period = Period::active();
        $session = [Period::WORKSPACE_KEY => $period->id];
        $values = ['supervise_min' => 1, 'supervise_max' => 4, 'examine_min' => 1, 'examine_max' => 3];
        [$a, $b] = [$this->user('dosen4@gmail.com'), $this->user('dosen5@gmail.com')];

        $this->actingAs($admin)->withSession($session)->get(route('admin.quotas.index'))->assertOk()
            ->assertSee('Tambah Kuota')->assertSee('Kuota Pembimbing')->assertSee('Kuota Penguji')->assertSee('Semua dosen');

        // Dosen tertentu wajib memilih dosen; boleh lebih dari satu sekaligus
        $this->actingAs($admin)->withSession($session)->post(route('admin.quotas.store'), ['scope' => 'dosen'] + $values)->assertSessionHasErrors('lecturer_ids');
        $this->actingAs($admin)->withSession($session)->post(route('admin.quotas.store'), ['scope' => 'dosen', 'lecturer_ids' => [$a->id, $b->id]] + $values)->assertSessionHasNoErrors();
        $quotas = \App\Models\LoadQuota::query()->where('period_id', $period->id)->where('scope', 'dosen')->whereIn('lecturer_id', [$a->id, $b->id])->get();
        $this->assertCount(2, $quotas);
        $this->assertSame(4, app(LoadBalanceService::class)->quotaFor($a, $period)['pembimbing']['max']);

        // Edit hanya nilai kuota; minimum ≤ maksimum tetap divalidasi
        $quota = $quotas->first();
        $this->actingAs($admin)->withSession($session)->put(route('admin.quotas.update', $quota), ['supervise_min' => 9] + $values)->assertSessionHasErrors('supervise_min');
        $this->actingAs($admin)->withSession($session)->put(route('admin.quotas.update', $quota), ['supervise_max' => 6] + $values)->assertSessionHasNoErrors();
        $this->assertSame(6, $quota->fresh()->supervise_max);

        // Reset: kuota dosen menjadi 0 (aturan per dosen bernilai 0)
        $this->actingAs($admin)->withSession($session)->post(route('admin.quotas.reset', $a))->assertSessionHasNoErrors();
        $this->assertSame(0, (new LoadBalanceService)->quotaFor($a, $period)['pembimbing']['max']);
        $this->assertSame(0, (new LoadBalanceService)->quotaFor($a, $period)['penguji']['max']);

        // Daftar dosen: kuota yang berlaku per dosen; hapus aturan per dosen → kembali ke global
        $this->actingAs($admin)->withSession($session)->get(route('admin.quotas.index'))->assertOk()
            ->assertSee('Kuota per dosen')->assertSee('Cari kode atau nama dosen')->assertSee('Aturan per dosen')->assertDontSee('Mengikuti aturan global');
        $this->actingAs($admin)->withSession($session)->delete(route('admin.quotas.destroy', $quota))->assertSessionHasNoErrors();
        $this->assertModelMissing($quota);
        $global = \App\Models\LoadQuota::query()->where('period_id', $period->id)->where('scope', 'global')->first();
        $this->assertSame((int) $global->supervise_max, (new LoadBalanceService)->quotaFor($quota->lecturer, $period)['pembimbing']['max']);
    }

    public function test_mark_all_notifications_read_in_background(): void
    {
        $user = $this->user('dosen@gmail.com');
        \App\Notifications\SystemNotice::sendTo([$user], 'Uji', 'Notifikasi uji.');
        $this->assertGreaterThan(0, $user->unreadNotifications()->count());

        // Lonceng memanggil di latar belakang (JSON) → 204 tanpa redirect
        $this->actingAs($user)->postJson(route('notifications.read-all'))->assertNoContent();
        $this->assertSame(0, $user->unreadNotifications()->count());

        // Tanpa JavaScript tetap kembali ke halaman asal
        $this->actingAs($user)->from(route('dosen.dashboard'))->post(route('notifications.read-all'))->assertRedirect(route('dosen.dashboard'));
    }

    public function test_lecturer_dashboard_greets_with_lecturer_code_and_roles(): void
    {
        $this->actingAs($this->user('dosen2@gmail.com'))->get(route('dosen.dashboard'))->assertOk()
            ->assertSee('Selamat datang, Bapak/Ibu HSP.')
            ->assertSee('Pembimbing')
            // Pengingat Penilaian menggantikan kartu Tim Mahasiswa
            ->assertSee('Pengingat Penilaian')->assertDontSee('Log disetujui');
    }

    public function test_lecturer_assessment_list_has_team_search_and_component_tabs(): void
    {
        $assignment = TeamLecturer::query()->where('role', 'pembimbing')
            ->whereHas('team', fn ($q) => $q->where('period_id', Period::active()->id)->where('status', 'diterima'))->firstOrFail();
        [$lecturer, $team] = [$assignment->lecturer, $assignment->team];

        // Tanpa dropdown filter; tab pertama = Proposal, berisi tim beserta anggota, jenis topik & peran
        $this->actingAs($lecturer)->get(route('dosen.assessments.index'))->assertOk()
            ->assertSee('Cari nama tim')->assertDontSee('Tim bimbingan')
            ->assertSee(route('dosen.assessments.index', ['komponen' => 'laporan_progress']))
            ->assertSee($team->name)->assertSee($team->members->first()->user->name)->assertSee('Jenis Topik')
            ->assertSee(e(route('dosen.assessments.edit', [$team, 'proposal'])), false);

        // Tab lain membuka formulir bentuk penilaian itu; tab di luar wewenang kembali ke tab pertama
        $this->actingAs($lecturer)->get(route('dosen.assessments.index', ['komponen' => 'laporan_progress']))->assertOk()
            ->assertSee(e(route('dosen.assessments.edit', [$team, 'laporan_progress'])), false)
            ->assertDontSee(e(route('dosen.assessments.edit', [$team, 'proposal'])), false);
        $this->actingAs($lecturer)->get(route('dosen.assessments.index', ['komponen' => 'tidak_ada']))->assertOk()
            ->assertSee(e(route('dosen.assessments.edit', [$team, 'proposal'])), false);
    }

    public function test_lecturer_team_menus_split_by_role_with_search_and_topic_filter(): void
    {
        $dosen3 = $this->user('dosen3@gmail.com');

        // dosen3: pembimbing Tim Lintang (topik mandiri), penguji Tim Arunika (topik dosen)
        $this->actingAs($dosen3)->get(route('dosen.teams.supervised'))->assertOk()
            ->assertSee('Tim Bimbingan')->assertSee('Tim Lintang')->assertDontSee('Tim Arunika')
            ->assertSee('Dimas Prakoso')->assertSee('Ketua')->assertSee('Topik Mandiri')->assertSee('Lihat detail');
        $this->actingAs($dosen3)->get(route('dosen.teams.examined'))->assertOk()
            ->assertSee('Tim Arunika')->assertDontSee('Tim Lintang')->assertSee('Topik Dosen');

        // Filter jenis topik & pencarian nama anggota
        $this->actingAs($dosen3)->get(route('dosen.teams.supervised', ['jenis' => 'dosen']))->assertOk()
            ->assertDontSee('Tim Lintang')->assertSee('Tidak ada tim yang cocok');
        $this->actingAs($dosen3)->get(route('dosen.teams.supervised', ['q' => 'Dimas']))->assertOk()->assertSee('Tim Lintang');
        $this->actingAs($dosen3)->get(route('dosen.teams.supervised', ['q' => 'tidak-ada']))->assertOk()->assertDontSee('Tim Lintang');
    }

    public function test_lecturer_team_detail_overlay_replaces_old_detail_page(): void
    {
        $dosen = $this->user('dosen@gmail.com');
        $team = Team::query()->where('name', 'Tim Arunika')->first();

        // Overlay berisi kelima kartu; ?tim= membukanya langsung
        $this->actingAs($dosen)->get(route('dosen.teams.supervised', ['tim' => $team->id]))->assertOk()
            ->assertSee('data-open="true"', false)
            ->assertSee('Topik dan Objek Final')->assertSee('Sesi Bimbingan')->assertSee('Deliverable')->assertSee('Hasil Penilaian')
            ->assertSee('Asal daerah')->assertSee('Lihat KHS')
            // Submission: tombol Nilai membuka penilaian bentuk penilaian dokumen itu (proposal → proposal, sebagai pembimbing)
            ->assertSee(e(route('dosen.assessments.edit', [$team, 'proposal'])), false)
            ->assertSee(e(route('dosen.assessments.edit', [$team, 'sidang_pembimbing'])), false)
            ->assertSee('sesi minimal')->assertSee('Syarat minimal terpenuhi')
            ->assertSee('Diisi mahasiswa (antar-anggota tim)')
            ->assertSee('Kemampuan komunikasi dan memfasilitasi tim')->assertSee('Sudah mengisi')->assertSee('Belum mengisi')
            // Rincian per penilai di bawah rata-rata: Alya sudah menilai, Bima belum
            ->assertSee('Alya <span class="tabular">', false)->assertSee('Bima <span class="tabular">0</span>', false)
            ->assertSee(e(route('dosen.logbooks.index', ['tab' => 'log'])).'#tim-'.$team->id, false);

        // Tombol "Lihat detail" sesi bimbingan menggulir ke tim tersebut di tabel History Bimbingan
        $this->actingAs($dosen)->get(route('dosen.logbooks.index', ['tab' => 'log']))->assertOk()
            ->assertSee('id="tim-'.$team->id.'"', false)->assertSee('History Bimbingan');

        // Halaman detail lama sudah dihapus
        $this->actingAs($dosen)->get('/dosen/tim/'.$team->id)->assertNotFound();

        // Dosen tim boleh membuka KHS anggota; dosen lain tidak
        $member = $team->members()->where('invite_status', 'diterima')->first();
        $member->update(['khs_path' => 'khs/test.pdf']);
        Storage::disk('local')->put('khs/test.pdf', '%PDF-1.4');
        $this->actingAs($dosen)->get(route('attachments.download', [$team, $member, 'khs']))->assertOk();
        $this->actingAs($this->user('dosen5@gmail.com'))->get(route('attachments.download', [$team, $member, 'khs']))->assertForbidden();
    }

    public function test_admin_adds_team_and_opens_detail_modal(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        [$leader, $member] = [$this->user('mahasiswa10@gmail.com'), $this->user('mahasiswa13@gmail.com')];

        // Semua isian wajib: minimal dua mahasiswa (ketua + anggota)
        $this->actingAs($admin)->post(route('admin.teams.store'), ['_team' => 'baru', 'name' => 'Tim Baru', 'members' => [$leader->id]])
            ->assertSessionHasErrors('members');

        $this->actingAs($admin)->post(route('admin.teams.store'), ['_team' => 'baru', 'name' => 'Tim Baru', 'members' => [$leader->id, $member->id]])
            ->assertSessionHasNoErrors();
        $team = Team::query()->where('name', 'Tim Baru')->sole();
        $this->assertSame('menunggu', $team->status);
        $this->assertSame($leader->id, $team->leader_id);
        $this->assertNotNull($team->screening);

        // Mahasiswa yang sudah bertim tidak bisa dipilih lagi
        $this->actingAs($admin)->post(route('admin.teams.store'), ['_team' => 'baru', 'name' => 'Tim Lain', 'members' => [$leader->id, $this->user('mahasiswa14@gmail.com')->id]])
            ->assertSessionHasErrors('members.0');

        // Tolak tanpa catatan boleh (catatan opsional); mahasiswa tetap mendapat notifikasi
        $this->actingAs($admin)->post(route('admin.teams.decide', $team), ['decision' => 'reject'])->assertSessionHasNoErrors();
        $this->assertSame('ditolak', $team->fresh()->status);
        $this->assertSame('Registrasi tim ditolak', $leader->notifications()->latest()->first()->data['title'] ?? null);

        // Tautan lama halaman detail membuka modal detail di daftar
        $this->actingAs($admin)->get(route('admin.teams.show', $team))->assertRedirect(route('admin.teams.index', ['tim' => $team->id]));
        $this->actingAs($admin)->get(route('admin.teams.index', ['tim' => $team->id]))->assertOk()
            ->assertSee('Hasil Screening')->assertDontSee('Tambah anggota (NIM)')->assertDontSee('Lihat Domisili')->assertSee('data-open="true"', false);
    }

    public function test_lecturer_timeline_shows_assessment_deadlines_only_for_lecturers(): void
    {
        $this->actingAs($this->user('dosen2@gmail.com'))->get(route('dosen.dashboard'))->assertOk()
            ->assertSee('Deadline penilaian dosen')
            ->assertSee('Penilaian Proposal')
            ->assertDontSee('Penilaian Peer Review');

        $this->actingAs($this->user('mahasiswa@gmail.com'))->get(route('mahasiswa.dashboard'))->assertOk()
            ->assertDontSee('Deadline penilaian dosen');
    }

    public function test_lecturer_sees_other_published_topics_but_manages_only_own(): void
    {
        $lecturer = $this->user('dosen@gmail.com');
        $others = Topic::query()->published()->where('lecturer_id', '!=', $lecturer->id)->first();
        $hiddenDraft = Topic::query()->where('source', 'dosen')->where('status', 'menunggu')->where('lecturer_id', '!=', $lecturer->id)->first();

        $page = $this->actingAs($lecturer)->get(route('dosen.topics.index'))->assertOk()->assertSee($others->title);
        if ($hiddenDraft) {
            $page->assertDontSee($hiddenDraft->title);
        }

        // Formulir tambah/edit tampil di atas halaman yang sama
        $own = Topic::query()->where('lecturer_id', $lecturer->id)->first();
        $this->actingAs($lecturer)->get(route('dosen.topics.index', ['edit' => $own->id]))->assertOk()->assertSee('Edit topik')->assertSee($own->object);
        $this->actingAs($lecturer)->get(route('dosen.topics.create'))->assertRedirect(route('dosen.topics.index', ['tambah' => 1]));
        $this->actingAs($lecturer)->get(route('dosen.topics.index', ['edit' => $others->id]))->assertForbidden();

        // Topik dosen lain tidak dapat diubah/dihapus
        $this->actingAs($lecturer)->get(route('dosen.topics.edit', $others))->assertForbidden();
        $this->actingAs($lecturer)->put(route('dosen.topics.update', $others), [])->assertForbidden();
        $this->actingAs($lecturer)->delete(route('dosen.topics.destroy', $others))->assertForbidden();
    }

    public function test_grade_index_follows_capstone_scale(): void
    {
        $calculator = app(ScoreCalculator::class);
        // Batas bawah eksklusif: A bila NSM > 85, AB bila 75 < NSM ≤ 85, dst.
        foreach ([
            [100, 'A', 4.0], [85.01, 'A', 4.0], [85, 'AB', 3.5], [75.01, 'AB', 3.5], [75, 'B', 3.0], [65.01, 'B', 3.0],
            [65, 'BC', 2.5], [60.01, 'BC', 2.5], [60, 'C', 2.0], [50.01, 'C', 2.0], [50, 'D', 1.0], [40.01, 'D', 1.0], [40, 'E', 0.0], [0, 'E', 0.0],
        ] as [$score, $grade, $point]) {
            $info = $calculator->gradeInfo($score);
            $this->assertSame([$grade, $point], [$info['grade'], $info['point']], "NSM {$score}");
            $this->assertSame($grade, $calculator->grade($score));
        }
    }

    public function test_student_sees_final_lecturer_scores_per_stage(): void
    {
        $team = Team::query()->where('name', 'Tim Arunika')->sole();
        $student = $team->activeUsers()->first();
        $calculator = app(ScoreCalculator::class);
        $proposal = $calculator->componentSummary($team)->firstWhere('component', 'proposal');
        $score = number_format($proposal['points'] / $proposal['max'] * 100, 2, ',', '.');

        // Draft dosen (belum final) tidak ditampilkan ke mahasiswa
        Assessment::create(['team_id' => $team->id, 'assessor_id' => $team->supervisor->lecturer_id, 'component' => 'laporan_progress',
            'assessor_role' => 'pembimbing', 'status' => 'draft', 'feedback' => 'Draf rahasia dosen']);

        $this->actingAs($student)->get(route('mahasiswa.assessments.index'))->assertOk()
            ->assertSee('Penilaian')->assertSee('Proposal')->assertSee('Sudah dinilai')->assertSee($score)
            ->assertSee('Contoh feedback: perkuat data pendukung')
            ->assertSee('Laporan Progress')->assertSee('Belum dinilai')->assertDontSee('Draf rahasia dosen')
            ->assertDontSee('Sumbangan ke nilai akhir')
            // Nilai akhir belum ditetapkan selama ada tahapan / peer review yang belum lengkap
            ->assertSee('Nilai Akhir')->assertSee('Menunggu: Laporan Progress');

        // Lengkapi semua tahapan dosen (skor 4 untuk setiap indikator) + peer review rekan (skor 4 tiap aspek)
        Assessment::query()->where('team_id', $team->id)->delete();
        foreach ($calculator->lecturerComponents() as $component) {
            $assessment = Assessment::create(['team_id' => $team->id, 'assessor_id' => $team->supervisor->lecturer_id, 'component' => $component,
                'assessor_role' => config("capstone.components.{$component}.role"), 'status' => 'final', 'submitted_at' => now()]);
            foreach ($calculator->indicatorsFor($component) as $indicator) {
                $assessment->scores()->create(['indicator_id' => $indicator->id, 'score' => 4, 'factor' => 1]);
            }
        }
        PeerReview::query()->where('team_id', $team->id)->delete();
        $members = $team->activeUsers()->get();
        foreach ($members as $reviewer) {
            foreach ($members->whereNotIn('id', [$reviewer->id]) as $reviewee) {
                foreach (array_keys(config('capstone.peer_aspects')) as $aspect) {
                    PeerReview::create(['team_id' => $team->id, 'reviewer_id' => $reviewer->id, 'reviewee_id' => $reviewee->id, 'aspect' => $aspect, 'score' => 4]);
                }
            }
        }

        // Ketentuan: nilai indikator = (skor/5) × bobot bentuk penilaian × faktor; nilai akhir = Σ seluruh nilai indikator (= Σ CLO)
        $expected = 0.0;
        foreach (array_keys(config('capstone.components')) as $component) {
            foreach ($calculator->indicatorsFor($component) as $indicator) {
                $expected += (4 / 5) * $indicator->weightFor($component);
            }
        }
        $expected = round($expected, 2);
        $grade = $calculator->gradeInfo($expected);
        $this->actingAs($student)->get(route('mahasiswa.assessments.index'))->assertOk()
            ->assertSee(number_format($expected, 2, ',', '.'))->assertSee('>'.$grade['grade'].'<', false)
            ->assertSeeText(number_format($grade['point'], 1, ',', '.').' · '.$grade['label'])->assertDontSee('Menunggu:');

        // Tim belum diterima → belum ada penilaian
        $this->actingAs($this->user('mahasiswa14@gmail.com'))->get(route('mahasiswa.assessments.index'))->assertOk()
            ->assertSee('Penilaian tersedia setelah registrasi tim diterima');
    }

    public function test_independent_topic_is_optional_and_parallel_with_lecturer_topics(): void
    {
        $period = Period::active();
        $leader = $this->user('mahasiswa14@gmail.com');
        $mate = $this->user('mahasiswa10@gmail.com');
        $mate->memberships()->delete();
        $leader->memberships()->delete();
        $team = Team::create(['period_id' => $period->id, 'name' => 'Tim Tanpa Mandiri',
            'leader_id' => $leader->id, 'status' => 'diterima', 'submitted_at' => now(), 'reviewed_at' => now()]);
        foreach ([$leader, $mate] as $i => $u) {
            $team->members()->create(['user_id' => $u->id, 'is_leader' => $i === 0, 'invite_status' => 'diterima', 'responded_at' => now()]);
        }
        $portfolio = ['portfolio_url' => 'https://drive.google.com/drive/folders/portofolio'];
        $bankTopic = Topic::query()->published()->where('period_id', $period->id)->get()->first(fn (Topic $t) => $t->remainingQuota() > 0);

        // Tanpa topik mandiri pun tim bisa langsung melamar topik dosen
        $this->actingAs($leader)->get(route('mahasiswa.topic.index', ['tab' => 'dosen']))->assertOk()
            ->assertDontSee('Pengajuan topik dosen dibuka setelah');
        $this->actingAs($leader)->post(route('mahasiswa.bank.apply', $bankTopic), $portfolio)->assertSessionHasNoErrors();
        $application = $team->applications()->sole();

        // Lamaran ditolak dan tim tidak mengajukan topik mandiri → belum ada topik final (konsekuensi tim)
        $this->actingAs($bankTopic->lecturer)->post(route('dosen.applications.decide', $application), ['decision' => 'reject', 'notes' => 'Belum sesuai'])
            ->assertSessionHasNoErrors();
        $this->assertNull($team->fresh()->final_topic_id);

        // Tim tetap boleh mengajukan topik mandiri; setelah diterima di topik dosen, topik mandiri ditutup
        $this->actingAs($leader)->get(route('mahasiswa.topic.index'))->assertOk()->assertSee('Pengajuan Topik Mandiri Tim')->assertDontSee('(opsional)');
        $other = Topic::query()->published()->where('period_id', $period->id)->whereKeyNot($bankTopic->id)->get()->first(fn (Topic $t) => $t->remainingQuota() > 0);
        $this->actingAs($leader)->post(route('mahasiswa.bank.apply', $other), $portfolio)->assertSessionHasNoErrors();
        $this->actingAs($other->lecturer)->post(route('dosen.applications.decide', $team->applications()->where('topic_id', $other->id)->sole()), ['decision' => 'approve'])
            ->assertSessionHasNoErrors();
        $this->assertSame($other->id, $team->fresh()->final_topic_id);
        $this->actingAs($leader)->post(route('mahasiswa.topic.store'), [
            'title' => 'Topik mandiri terlambat', 'description' => str_repeat('Permasalahan integrasi data. ', 3), 'object' => 'PT Contoh',
        ])->assertSessionHasErrors('title');
    }

    public function test_team_may_hold_several_pending_applications_until_one_is_accepted(): void
    {
        $student = $this->user('mahasiswa4@gmail.com');                  // Tim Lintang: 1 pengajuan menunggu (topik dsn3)
        $team = $student->teamFor(Period::active());
        $pending = $team->applications()->where('status', 'menunggu')->firstOrFail();
        $another = Topic::query()->published()->where('title', 'like', 'Dashboard%')->firstOrFail();
        $portfolio = ['portfolio_url' => 'https://drive.google.com/x'];

        // Masih menunggu → tetap boleh mengajukan topik lain, tetapi tidak topik yang sama dua kali
        $this->actingAs($student)->post(route('mahasiswa.bank.apply', $another), $portfolio)->assertSessionHasNoErrors();
        $this->actingAs($student)->post(route('mahasiswa.bank.apply', $another), $portfolio)->assertSessionHasErrors('topic');
        $this->assertSame(2, $team->applications()->where('status', 'menunggu')->count());

        // To Do dosen memuat pengajuan yang perlu diputuskan
        $this->actingAs($this->user('dosen3@gmail.com'))->get(route('dosen.dashboard'))->assertOk()
            ->assertSee('To Do')->assertSee('Putuskan pengajuan '.$team->name);

        // Halaman utama menampilkan kuota topik; pelamar tampil di halaman pengajuan topiknya
        $this->actingAs($this->user('dosen3@gmail.com'))->get(route('dosen.applications.index'))->assertOk()
            ->assertSee('Topik Saya')->assertSee('Lihat Pengajuan');
        $this->actingAs($this->user('dosen3@gmail.com'))->get(route('dosen.applications.show', $pending->topic))->assertOk()
            ->assertSee($team->name);

        // Satu diterima → pengajuan lain otomatis dibatalkan dan pengajuan baru ditolak
        $this->actingAs($this->user('dosen3@gmail.com'))->post(route('dosen.applications.decide', $pending), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('dibatalkan', $team->applications()->where('topic_id', $another->id)->value('status'));
        $third = Topic::query()->published()->whereNotIn('id', [$another->id, $pending->topic_id])->firstOrFail();
        $this->actingAs($student)->post(route('mahasiswa.bank.apply', $third), $portfolio)->assertSessionHasErrors('topic');

        // Penolakan boleh tanpa alasan
        $other = TopicApplication::create(['topic_id' => $third->id, 'team_id' => Team::where('name', 'Tim Bentala')->value('id')]);
        $this->actingAs($third->lecturer)->post(route('dosen.applications.decide', $other), ['decision' => 'reject'])->assertSessionHasNoErrors();
        $this->assertSame('ditolak', $other->fresh()->status);
    }

    public function test_quota_full_blocks_application(): void
    {
        $topic = Topic::query()->published()->where('title', 'like', 'Dashboard%')->first();
        $topic->update(['quota' => 1]);
        $t2 = Team::query()->where('name', 'Tim Lintang')->first();
        TopicApplication::create(['topic_id' => $topic->id, 'team_id' => Team::where('name', 'Tim Bentala')->value('id'), 'status' => 'diterima']);
        $t2->applications()->delete();

        $this->actingAs($this->user('mahasiswa4@gmail.com'))->post(route('mahasiswa.bank.apply', $topic), ['portfolio_url' => 'https://drive.google.com/x'])
            ->assertSessionHasErrors(['topic' => 'Kuota topik ini sudah penuh. Pilih topik lain.']);
    }

    public function test_logbook_gate_and_review(): void
    {
        $team = Team::query()->where('name', 'Tim Lintang')->first();
        $student = $this->user('mahasiswa4@gmail.com');
        $lecturer = $this->user('dosen3@gmail.com');

        // Notes opsional: tautan menjadi link yang dapat diklik, teks lain tetap di-escape
        $this->actingAs($student)->post(route('mahasiswa.logbooks.store'), [
            'session_date' => now()->toDateString(), 'activity' => 'Validasi kebutuhan',
            'notes' => 'Slide: https://drive.google.com/file/d/abc. <b>tebal</b>',
        ])->assertSessionHasNoErrors();
        $log = Logbook::query()->where('team_id', $team->id)->latest('id')->first();
        $this->assertSame(4, $log->session_no);
        $this->assertSame('Slide: https://drive.google.com/file/d/abc. <b>tebal</b>', $log->notes);
        $link = '<a href="https://drive.google.com/file/d/abc" target="_blank" rel="noopener noreferrer" class="link break-all">https://drive.google.com/file/d/abc</a>.';

        // Log baru tampil di kartu Pengajuan Log Bimbingan pembimbing, beserta notes-nya
        $this->actingAs($lecturer)->get(route('dosen.logbooks.index'))->assertOk()
            ->assertSee('Pengajuan Log Bimbingan')->assertSee('Validasi kebutuhan')
            ->assertSee($link, false)->assertSee('&lt;b&gt;tebal&lt;/b&gt;', false);

        $this->actingAs($lecturer)->get(route('dosen.logbooks.index', ['tab' => 'log']))->assertOk()
            ->assertSee('Jumlah Tim Bimbingan')->assertSee('Validasi kebutuhan')->assertSee('Tanggal bimbingan')->assertSee($link, false);
        $this->actingAs($student)->get(route('mahasiswa.logbooks.index'))->assertOk()->assertSee('Notes')->assertSee($link, false);

        // Dosen lain tidak boleh meninjau (F46)
        $this->actingAs($this->user('dosen@gmail.com'))->post(route('dosen.logbooks.review', $log), ['decision' => 'approve'])->assertForbidden();

        // Tolak boleh tanpa feedback
        $this->actingAs($lecturer)->post(route('dosen.logbooks.review', $log), ['decision' => 'reject'])->assertSessionHasNoErrors();
        $this->assertSame('revisi', $log->fresh()->status);

        $this->actingAs($student)->put(route('mahasiswa.logbooks.update', $log), [
            'session_date' => now()->toDateString(), 'activity' => 'Validasi kebutuhan beserta daftar kebutuhan tervalidasi',
        ])->assertSessionHasNoErrors();
        $this->assertSame('menunggu', $log->fresh()->status);
        $this->assertGreaterThanOrEqual(3, $log->histories()->count(), 'Histori revisi tersimpan (PRD 6.6)');

        // Gate presentasi akhir: < 8 log disetujui → sidang tidak dapat dinilai
        $window = $team->period->assessmentWindows()->where('component', 'sidang_pembimbing')->first();
        $window->update(['opens_at' => now()->subDay(), 'due_at' => now()->addDay()]);
        $this->actingAs($lecturer)->put(route('dosen.assessments.update', [$team, 'sidang_pembimbing']), ['feedback' => 'Catatan'])
            ->assertSessionHasErrors('gate');
    }

    public function test_assessment_formula_and_recap(): void
    {
        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $calculator = app(ScoreCalculator::class);
        $indicator = $calculator->indicatorsFor('proposal')->firstWhere('code', '1.1');

        // (Skor / 5) × Bobot × Faktor — indikator 1.1 bobot proposal 2, skor 4, faktor 1 → 1,6
        $this->assertEqualsWithDelta(1.6, $calculator->indicatorValue($indicator, 'proposal', 4), 0.001);

        $oral = $calculator->indicators()->firstWhere('code', '6.2');
        $this->assertTrue($oral->usesOralFactor());
        // 6.2 (Lisan): bobot sidang_pembimbing 2, skor 5, faktor 0,5 → 1
        $this->assertEqualsWithDelta(1.0, $calculator->indicatorValue($oral, 'sidang_pembimbing', 5, 0.5), 0.001);

        $result = $calculator->forTeam($team);
        $this->assertFalse($result['complete']);
        $this->assertNotContains('proposal', $result['missing']);
        $this->assertContains('peer_review', $result['missing']);

        // Ekspor penuh diblokir bila belum lengkap; ekspor iGadis tersedia
        $admin = User::query()->where('role', 'admin')->first();
        $this->actingAs($admin)->get(route('admin.recap.export'))->assertSessionHasErrors('export');
        $this->actingAs($admin)->get(route('admin.recap.export', ['mode' => 'igadis']))->assertOk()->assertDownload();
        $this->actingAs($admin)->get(route('admin.recap.index'))->assertOk()->assertSee('Ekspor iGadis')->assertSee('Nama Tim')->assertSee($team->name);

        // Format template iGadis: identitas A–F (digabung 2 baris), kolom bentuk penilaian × CLO berbobot, rumus total
        $path = app(\App\Services\RecapExporter::class)->toIgadis(collect([['team' => $team] + $result]));
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        unlink($path);
        $this->assertSame(['COURSEID', 'SUBJECTCODE', 'CLASS', 'STUDENTID', 'OLDSTUDENTID', 'FULLNAME'],
            array_map(fn ($c) => $sheet->getCell("{$c}1")->getValue(), ['A', 'B', 'C', 'D', 'E', 'F']));
        $this->assertContains('A1:A2', $sheet->getMergeCells());
        $this->assertSame('Proposal [CLO1] (6%)', $sheet->getCell('G1')->getValue());
        $this->assertSame('1 (100%)', $sheet->getCell('G2')->getValue());
        $student = $result['rows']->first()['student'];
        $this->assertSame((string) $student->nim, (string) $sheet->getCell('D3')->getValue());
        $this->assertSame($student->name, $sheet->getCell('F3')->getValue());
        $this->assertNull($sheet->getCell('A3')->getValue());
        $this->assertIsNumeric($sheet->getCell('G3')->getValue());      // proposal sudah final
        $headers = collect($sheet->rangeToArray('G1:'.$sheet->getHighestColumn().'1')[0]);
        $peerCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(7 + $headers->search(fn ($h) => str_starts_with((string) $h, 'Peer Review')));
        $this->assertNull($sheet->getCell("{$peerCol}3")->getValue());  // peer review belum lengkap → kosong
        $this->assertStringStartsWith('=(G3*0.06+', (string) $sheet->getCell($sheet->getHighestColumn().'3')->getValue());
    }

    public function test_lecturer_scores_checked_indicators_then_submits_final(): void
    {
        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $lecturer = $team->supervisor->lecturer;
        $team->period->assessmentWindows()->where('component', 'proposal')->update(['opens_at' => now()->subDay(), 'due_at' => now()->addDay()]);
        // Mulai dari kosong (DemoSeeder sudah memberi nilai final proposal tim ini)
        $team->assessments()->where('component', 'proposal')->delete();
        $indicators = app(ScoreCalculator::class)->indicatorsFor('proposal');
        $clo1 = $indicators->where('clo_id', $indicators->first()->clo_id)->values();
        $route = fn (string $name) => route($name, [$team, 'proposal']);

        // Tabel rubrik + toolbar progres / skor / Simpan penilaian + keterangan warna
        $this->actingAs($lecturer)->get($route('dosen.assessments.edit'))->assertOk()
            ->assertSee('indikator sudah dinilai')->assertSee('Hapus<span class="hidden sm:inline"> pilihan</span>', false)->assertSee('Bekukan kolom indikator')->assertSee('Sudah dinilai')->assertSee('Belum dinilai')->assertSee('5 - Menguasai penuh (1,00)')
            ->assertSee('Contoh Pemenuhan Tingkat 5')->assertSee($clo1->first()->rubric->level_5);

        // Wajib mencentang indikator & memilih skor; indikator lisan wajib pemahaman lisan
        $this->actingAs($lecturer)->put($route('dosen.assessments.scores'), ['score' => 4])->assertSessionHasErrors('indicator_ids');
        $this->actingAs($lecturer)->put($route('dosen.assessments.scores'), ['indicator_ids' => $clo1->pluck('id')->all(), 'score' => 4])->assertSessionHasErrors('oral_level');

        // Satu CLO sekaligus (beberapa indikator), lalu satu indikator diubah sendiri
        $this->actingAs($lecturer)->put($route('dosen.assessments.scores'), ['indicator_ids' => $clo1->pluck('id')->all(), 'score' => 4, 'oral_level' => 5])->assertSessionHasNoErrors();
        $this->actingAs($lecturer)->put($route('dosen.assessments.scores'), ['indicator_ids' => [$clo1->last()->id], 'score' => 2, 'oral_level' => 3])->assertSessionHasNoErrors();
        $assessment = $team->assessments()->where('component', 'proposal')->where('assessor_id', $lecturer->id)->first();
        $this->assertSame(4, $assessment->scores()->where('indicator_id', $clo1->first()->id)->value('score'));
        $this->assertSame(2, $assessment->scores()->where('indicator_id', $clo1->last()->id)->value('score'));
        $this->assertEquals(0.75, $assessment->scores()->where('indicator_id', $clo1->last()->id)->value('factor'));

        // Hapus pilihan: skor indikator yang dicentang dikosongkan, lalu dinilai lagi
        $this->actingAs($lecturer)->delete($route('dosen.assessments.scores.destroy'), ['indicator_ids' => [$clo1->last()->id]])->assertSessionHasNoErrors();
        $this->assertNull($assessment->scores()->where('indicator_id', $clo1->last()->id)->first());
        $this->actingAs($lecturer)->put($route('dosen.assessments.scores'), ['indicator_ids' => [$clo1->last()->id], 'score' => 2, 'oral_level' => 3])->assertSessionHasNoErrors();

        // Real time: sebagian dinilai → status draft (belum masuk rekap); tidak ada lagi tombol draf / final
        $this->actingAs($lecturer)->get($route('dosen.assessments.edit'))->assertOk()
            ->assertSee($clo1->count().'</span> dari <span class="tabular">'.$indicators->count().'</span> indikator sudah dinilai', false)
            ->assertSee('Sebagian dinilai')->assertDontSee('Kirim nilai final')->assertDontSee('Simpan draf');
        $this->assertSame('draft', $assessment->fresh()->status);

        // Semua indikator dinilai → otomatis lengkap (final) tanpa tombol kirim; feedback opsional
        $rest = $indicators->whereNotIn('id', $clo1->pluck('id'))->pluck('id')->all();
        $this->actingAs($lecturer)->put($route('dosen.assessments.scores'), ['indicator_ids' => $rest, 'score' => 5, 'oral_level' => 5])->assertSessionHasNoErrors();
        $this->assertSame('final', $assessment->fresh()->status);
        $this->assertNotNull($assessment->fresh()->submitted_at);
        $this->actingAs($lecturer)->put($route('dosen.assessments.update'), ['feedback' => 'Baik'])->assertSessionHasNoErrors();
        $this->assertSame(['Baik', 'final'], [$assessment->fresh()->feedback, $assessment->fresh()->status]);

        // Menghapus skor → tidak lengkap lagi, kembali sebagian (draft)
        $this->actingAs($lecturer)->delete($route('dosen.assessments.scores.destroy'), ['indicator_ids' => [$clo1->first()->id]])->assertSessionHasNoErrors();
        $this->assertSame('draft', $assessment->fresh()->status);
    }

    public function test_assessment_master_matches_rubric_workbook(): void
    {
        $calculator = app(ScoreCalculator::class);
        $this->assertSame(8, \App\Models\Clo::query()->count());
        $this->assertSame(30, \App\Models\Indicator::query()->count());
        $this->assertSame(30, \App\Models\DocumentRubric::query()->count());
        $this->assertSame([5, 4, 3, 2, 1], \App\Models\OralRubricLevel::query()->orderByDesc('level')->pluck('level')->all());
        $summary = $calculator->weightSummary();
        $this->assertEquals(100, $summary['clo_total']);
        foreach ($summary['components'] as $component => $totals) {
            $this->assertEquals($totals['clo'], $totals['indicator'], "Bobot CLO = bobot indikator pada {$component}");
        }
        $this->assertSame('dokumen_lisan', \App\Models\Indicator::query()->where('code', '1.1')->value('scheme'));
    }

    public function test_admin_manages_clos_in_bulk(): void
    {
        $admin = $this->user('admin@gmail.com');
        $clo8 = \App\Models\Clo::query()->where('code', 'CLO8')->firstOrFail();

        // Tabel resume: CLO · Komponen Penilaian · Skema · Bobot, total di bawah
        $this->actingAs($admin)->get(route('admin.clos.index'))->assertOk()
            ->assertSee('Tambah CLO')->assertSee('Edit Pilihan')->assertSee('Hapus Pilihan')
            ->assertSee('Komponen Penilaian')->assertSee('Total Penilaian')->assertSee('Mampu melaksanakan kerja tim');

        // Tambah: kode unik; bobot = jumlah bobot per bentuk penilaian
        $base = ['description' => 'Mampu menguji coba', 'assessment_scheme' => 'Capstone Design dan Project'];
        $this->actingAs($admin)->post(route('admin.clos.store'), $base + ['code' => 'CLO1'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post(route('admin.clos.store'), $base + ['code' => 'clo9', 'weights' => ['proposal' => 2, 'pameran' => 1]])->assertSessionHasNoErrors();
        $clo9 = \App\Models\Clo::query()->where('code', 'CLO9')->firstOrFail();
        $this->assertEquals(3, $clo9->weight);

        // Edit Pilihan: beberapa CLO sekaligus (boleh bertukar kode), kode bentrok ditolak
        $this->actingAs($admin)->put(route('admin.clos.update-many'), ['rows' => [
            $clo9->id => ['code' => 'CLO1'] + $base,
        ]])->assertSessionHasErrors("rows.{$clo9->id}.code");
        $this->actingAs($admin)->put(route('admin.clos.update-many'), ['rows' => [
            $clo9->id => ['code' => 'CLO10', 'description' => 'Deskripsi baru', 'weights' => ['proposal' => 4]] + $base,
            $clo8->id => ['code' => 'CLO8', 'description' => $clo8->description, 'assessment_scheme' => $clo8->assessment_scheme,
                'weights' => ['sidang_pembimbing' => 3, 'sidang_penguji' => 2]],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['CLO10', 'Deskripsi baru', 4.0], [$clo9->fresh()->code, $clo9->fresh()->description, $clo9->fresh()->weight]);

        // Hapus Pilihan: CLO yang masih punya indikator ditolak (tidak ada yang terhapus)
        $this->actingAs($admin)->delete(route('admin.clos.destroy-many'), ['ids' => [$clo9->id, $clo8->id]])->assertSessionHasErrors('ids');
        $this->assertModelExists($clo9);
        $this->actingAs($admin)->delete(route('admin.clos.destroy-many'), ['ids' => [$clo9->id]])->assertSessionHasNoErrors();
        $this->assertModelMissing($clo9);
    }

    public function test_admin_manages_indicators_and_components_in_bulk(): void
    {
        $admin = $this->user('admin@gmail.com');
        $clo1 = \App\Models\Clo::query()->where('code', 'CLO1')->firstOrFail();
        $base = ['clo_id' => $clo1->id, 'name' => 'Indikator uji', 'evidence' => 'Bab 1', 'scheme' => 'dokumen'];

        // Tabel seperti lembar Indikator: centang baris, centang judul kolom bentuk penilaian, baris total
        $this->actingAs($admin)->get(route('admin.indicators.index'))->assertOk()
            ->assertSee('Tambah Indikator')->assertSee('Edit Pilihan')->assertSee('Hapus Pilihan')
            ->assertSee('Bukti / Bab')->assertSee('Skema Penilaian')->assertSee('data-select-col', false)
            ->assertSee('Identifikasi Permasalahan Riil');

        // Tambah: ID unik, bobot % = jumlah bobot per bentuk penilaian
        $this->actingAs($admin)->post(route('admin.indicators.store'), $base + ['code' => '1.1'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->post(route('admin.indicators.store'), $base + ['code' => '1.9', 'weights' => ['proposal' => 1, 'pameran' => 0.5]])->assertSessionHasNoErrors();
        $new = \App\Models\Indicator::query()->where('code', '1.9')->firstOrFail();
        $this->assertEquals(1.5, $new->weight);

        // Edit Pilihan: indikator + bentuk penilaian sekaligus; peer review tidak dapat diubah
        $this->actingAs($admin)->put(route('admin.indicators.update-many'), [
            'components' => ['peer_review' => ['label' => 'Peer', 'roles' => ['pembimbing']]],
        ])->assertSessionHasErrors('components.peer_review.label');
        $this->actingAs($admin)->put(route('admin.indicators.update-many'), [
            'rows' => [$new->id => ['code' => '1.8', 'name' => 'Indikator baru', 'weights' => ['proposal' => 2]] + $base],
            'components' => ['pameran' => ['label' => 'Pameran Prototipe', 'roles' => ['penguji', 'pembimbing']]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['1.8', 'Indikator baru', 2.0], [$new->fresh()->code, $new->fresh()->name, $new->fresh()->weight]);
        $pameran = \App\Models\AssessmentComponent::query()->where('key', 'pameran')->first();
        $this->assertSame(['Pameran Prototipe', ['pembimbing', 'penguji']], [$pameran->label, $pameran->roles]);

        // Hapus Pilihan: indikator yang sudah dinilai / bentuk penilaian yang sudah bernilai ditolak; selain itu terhapus
        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $used = \App\Models\Indicator::query()->where('code', '1.1')->first();
        $assessment = Assessment::query()->firstOrCreate(['team_id' => $team->id, 'assessor_id' => $team->supervisor->lecturer_id, 'component' => 'proposal'], ['assessor_role' => 'pembimbing', 'status' => 'draft']);
        $assessment->scores()->updateOrCreate(['indicator_id' => $used->id], ['score' => 4, 'factor' => 1]);
        $this->actingAs($admin)->delete(route('admin.indicators.destroy-many'), ['ids' => [$used->id]])->assertSessionHasErrors('selection');
        $this->actingAs($admin)->delete(route('admin.indicators.destroy-many'), ['components' => ['proposal']])->assertSessionHasErrors('selection');
        $this->actingAs($admin)->delete(route('admin.indicators.destroy-many'), ['ids' => [$new->id], 'components' => ['pameran']])->assertSessionHasNoErrors();
        $this->assertModelMissing($new);
        $this->assertNull(\App\Models\AssessmentComponent::query()->where('key', 'pameran')->first());
        $this->assertSame(0, DB::table('indicator_component_weights')->where('component', 'pameran')->count());
    }

    public function test_score_calculation_card_is_information_only(): void
    {
        $admin = $this->user('admin@gmail.com');
        $calculator = app(ScoreCalculator::class);
        $docLisan = $calculator->indicators()->firstWhere('code', '1.1');   // Dokumen + Lisan, bobot proposal 2
        $dokumen = $calculator->indicators()->firstWhere('code', '6.1');    // Dokumen, bobot sidang pembimbing 2

        // Kartu informasi di atas tabel indikator, isi dari berkas rubrik, tanpa tombol ubah
        $this->actingAs($admin)->get(route('admin.indicators.index'))->assertOk()
            ->assertSee('Cara nilai dihitung')->assertSee('Faktor Pemahaman')->assertSee('gerbang anti-AI')->assertDontSee('Tambah Baris');

        // Faktor lisan hanya untuk skema lisan; indikator lain dikali 1,00
        $this->assertEqualsWithDelta(0.8, $calculator->indicatorValue($docLisan, 'proposal', 4, 0.5), 0.001);
        $this->assertEqualsWithDelta(2.0, $calculator->indicatorValue($dokumen, 'sidang_pembimbing', 5, 0.5), 0.001);
    }

    public function test_admin_manages_rubrics_in_bulk(): void
    {
        $admin = $this->user('admin@gmail.com');
        [$a, $b] = \App\Models\Indicator::query()->orderBy('code')->take(2)->get()->all();
        $levels = collect(range(0, 5))->mapWithKeys(fn ($l) => ["level_{$l}" => "Deskripsi tingkat {$l}"])->all();

        $this->actingAs($admin)->get(route('admin.rubrics.index'))->assertOk()
            ->assertSee('Tambah Rubrik')->assertSee('Edit Pilihan')->assertSee('Hapus Pilihan')
            ->assertSee('5 - Sangat Baik (95)')->assertSee('Contoh Pemenuhan Tingkat 5')->assertSee($a->rubric->level_5);

        // Hapus Pilihan → indikator tetap, rubrik hilang; Tambah: semua wajib kecuali poin penilaian
        $this->actingAs($admin)->delete(route('admin.rubrics.destroy-many'), ['ids' => [$a->id, $b->id]])->assertSessionHasNoErrors();
        $this->assertNull($a->rubric()->first());
        $this->assertModelExists($a);
        $this->actingAs($admin)->post(route('admin.rubrics.store'), ['indicator_id' => $a->id] + $levels)->assertSessionHasErrors('example_level_5');
        $this->actingAs($admin)->post(route('admin.rubrics.store'), ['indicator_id' => $a->id, 'example_level_5' => 'Contoh'] + $levels)->assertSessionHasNoErrors();
        $this->assertNull($a->rubric()->first()->assessment_points);
        $this->actingAs($admin)->post(route('admin.rubrics.store'), ['indicator_id' => $b->id, 'example_level_5' => 'Contoh', 'assessment_points' => 'Poin'] + $levels)->assertSessionHasNoErrors();

        // Edit Pilihan: beberapa rubrik sekaligus; data indikator (kode, nama) tidak dapat diubah dari sini
        $this->actingAs($admin)->put(route('admin.rubrics.update-many'), ['rows' => [
            $a->id => ['example_level_5' => 'Baru A', 'code' => '9.9', 'name' => 'Nama lain'] + $levels,
            $b->id => ['example_level_5' => 'Baru B', 'assessment_points' => 'Poin B'] + $levels,
        ]])->assertSessionHasNoErrors();
        $this->assertSame('Baru A', $a->rubric()->first()->example_level_5);
        $this->assertSame('Poin B', $b->rubric()->first()->assessment_points);
        $this->assertSame([$a->code, $a->name], [$a->fresh()->code, $a->fresh()->name]);

        // Rubrik lisan: tabel, Tambah (semua wajib), Edit Pilihan (level unik), Hapus Pilihan
        $this->actingAs($admin)->get(route('admin.oral.index'))->assertOk()->assertSee('Tambah Level')->assertSee('Perilaku teramati pada verifikasi lisan')->assertSee('Menguasai penuh');
        [$l5, $l4] = \App\Models\OralRubricLevel::query()->orderByDesc('level')->take(2)->get()->all();
        $this->actingAs($admin)->put(route('admin.oral.update-many'), ['rows' => [
            $l5->id => ['level' => 4, 'factor' => 1, 'label' => 'A', 'behavior' => 'x'],
        ]])->assertSessionHasErrors("rows.{$l5->id}.level");
        $this->actingAs($admin)->put(route('admin.oral.update-many'), ['rows' => [
            $l5->id => ['level' => 5, 'factor' => 1, 'label' => 'Menguasai penuh', 'behavior' => 'Baru 5'],
            $l4->id => ['level' => 4, 'factor' => 0.9, 'label' => 'Menguasai', 'behavior' => 'Baru 4'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame('Baru 4', $l4->fresh()->behavior);
        $this->actingAs($admin)->delete(route('admin.oral.destroy-many'), ['ids' => [$l4->id]])->assertSessionHasNoErrors();
        $this->assertModelMissing($l4);
        $this->actingAs($admin)->post(route('admin.oral.store'), ['level' => 4, 'factor' => 0.9, 'label' => 'Menguasai'])->assertSessionHasErrors('behavior');
        $this->actingAs($admin)->post(route('admin.oral.store'), ['level' => 4, 'factor' => 0.9, 'label' => 'Menguasai', 'behavior' => 'B'])->assertSessionHasNoErrors();
    }

    public function test_lecturer_only_assesses_own_component(): void
    {
        $team = Team::query()->where('name', 'Tim Arunika')->first();
        // dsn3 adalah penguji Tim Arunika → tidak boleh menilai proposal (komponen pembimbing)
        $this->actingAs($this->user('dosen3@gmail.com'))->get(route('dosen.assessments.edit', [$team, 'proposal']))->assertForbidden();
        $this->actingAs($this->user('dosen5@gmail.com'))->get(route('dosen.assessments.edit', [$team, 'proposal']))->assertForbidden();
    }

    public function test_peer_review_flow(): void
    {
        $student = $this->user('mahasiswa@gmail.com');

        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $scores = array_fill_keys(array_keys(config('capstone.peer_aspects')), 4);
        $mates = $team->activeUsers()->whereKeyNot($student->id)->pluck('users.id');

        // Terbuka selama periode berlangsung: tanpa menunggu Laporan Akhir maupun jadwal milestone
        $this->assertFalse($team->documents()->where('document_type', 'laporan_akhir')->exists());
        $this->actingAs($student)->get(route('mahasiswa.peer.index'))->assertOk()->assertSee('Terbuka selama periode berlangsung')->assertDontSee('disabled title=', false);

        // Setiap rekan dinilai terpisah (6 aspek per rekan)
        $this->actingAs($student)->post(route('mahasiswa.peer.store'), ['reviewee_id' => $mates[0], 'scores' => $scores])->assertSessionHasNoErrors();
        $this->assertSame(6, PeerReview::query()->where('reviewer_id', $student->id)->count());

        // Bukan rekan satu tim → ditolak
        $this->actingAs($student)->post(route('mahasiswa.peer.store'), ['reviewee_id' => $this->user('mahasiswa4@gmail.com')->id, 'scores' => $scores])->assertSessionHasErrors('reviewee_id');

        foreach ($mates as $id) {
            $this->actingAs($student)->post(route('mahasiswa.peer.store'), ['reviewee_id' => $id, 'scores' => $scores])->assertSessionHasNoErrors();
        }
        // Overwrite, bukan menambah baris
        $this->assertSame(12, PeerReview::query()->where('reviewer_id', $student->id)->count());

        // Boleh dikirim ulang berkali-kali; nilai terbaru yang dipakai
        $this->actingAs($student)->post(route('mahasiswa.peer.store'), ['reviewee_id' => $mates[0], 'scores' => array_fill_keys(array_keys($scores), 2)])->assertSessionHasNoErrors();
        $this->assertSame(12, PeerReview::query()->where('reviewer_id', $student->id)->count());
        $this->assertSame([2], PeerReview::query()->where('reviewer_id', $student->id)->where('reviewee_id', $mates[0])->distinct()->pluck('score')->all());

        // Menu Hasil Penilaian mahasiswa sudah dihapus (nilai dilihat di sistem eksternal)
        $this->actingAs($student)->get('/mahasiswa/nilai')->assertNotFound();
    }

    public function test_final_report_requires_minimum_approved_logbooks(): void
    {
        // Tim Lintang baru 2 log disetujui (< 8)
        $student = $this->user('mahasiswa4@gmail.com');
        $team = Team::query()->where('name', 'Tim Lintang')->first();
        $report = $team->period->documentMilestone('laporan_akhir');
        $report->update(['starts_at' => now()->subDay()]);
        $upload = fn () => $this->actingAs($student)->post(route('mahasiswa.documents.store', $report), ['file' => UploadedFile::fake()->create('laporan.pdf', 200, 'application/pdf'), 'idempotency_key' => (string) Str::uuid()]);

        if ($team->documentUploadBlocker()) {
            $this->markTestSkipped('Tim demo Tim Lintang belum boleh mengunggah dokumen apa pun.');
        }
        $upload()->assertSessionHasErrors(['file' => 'Minimal 8 log bimbingan harus disetujui sebelum mengumpulkan '.$report->name.'.']);
        $this->actingAs($student)->get(route('mahasiswa.documents.index'))->assertSee('Terkunci — minimal 8 log bimbingan disetujui (2/8).');

        // Setelah 8 log disetujui → boleh mengumpulkan
        $logs = Logbook::query()->where('team_id', $team->id)->pluck('id');
        foreach (range($logs->count() + 1, 8) as $no) {
            Logbook::query()->create(['team_id' => $team->id, 'created_by' => $student->id, 'session_no' => $no, 'session_date' => now()->subDays(10 - $no), 'activity' => "Bimbingan {$no}", 'status' => 'disetujui']);
        }
        Logbook::query()->where('team_id', $team->id)->update(['status' => 'disetujui']);
        $upload()->assertSessionHasNoErrors();
    }

    public function test_document_upload_is_idempotent_and_team_scoped(): void
    {
        $student = $this->user('mahasiswa2@gmail.com');
        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $milestone = $team->period->documentMilestone('proposal');
        $teamDocument = fn () => Document::query()->where('team_id', $team->id)->where('milestone_id', $milestone->id)->where('state', 'aktif')->sole();
        $before = $teamDocument()->versions()->count();
        $key = (string) Str::uuid();
        $file = UploadedFile::fake()->create('proposal.pdf', 200, 'application/pdf');

        // Pengumpulan per tim: unggahan anggota lain menjadi revisi dokumen tim yang sama
        $this->actingAs($student)->post(route('mahasiswa.documents.store', $milestone), ['file' => $file, 'idempotency_key' => $key])->assertSessionHasNoErrors();
        $this->actingAs($student)->post(route('mahasiswa.documents.store', $milestone), ['file' => $file, 'idempotency_key' => $key])->assertSessionHasNoErrors();
        $document = $teamDocument();
        $this->assertSame($before + 1, $document->versions()->count(), 'Kirim ulang dengan kunci sama tidak membuat duplikat (F41)');
        $this->assertSame($student->id, $document->latestVersion()->uploaded_by);

        // Revisi
        $this->actingAs($student)->post(route('mahasiswa.documents.store', $milestone), ['file' => UploadedFile::fake()->create('v2.pdf', 200, 'application/pdf'), 'idempotency_key' => (string) Str::uuid()]);
        $this->assertSame($before + 2, $document->versions()->count());

        // Nama file otomatis "Namadokumen_Namatim.ext"; tiap revisi tersimpan terpisah walau namanya sama
        [$first, $second] = $document->versions()->latest('revision_no')->take(2)->get()->reverse()->values()->all();
        $expected = $milestone->name.'_'.$team->name.'.pdf';
        $this->assertSame([$expected, $expected], [$first->stored_name, $second->stored_name]);
        $this->assertNotSame($first->local_path, $second->local_path);
        $this->assertStringContainsString($expected, $this->actingAs($student)->get(route('documents.download', $second))->headers->get('Content-Disposition'));

        // F36 — sesama anggota tim boleh membuka dokumen tim; mahasiswa tim lain tidak
        $version = $document->latestVersion();
        $this->actingAs($this->user('mahasiswa@gmail.com'))->get(route('documents.download', $version))->assertOk();
        $this->actingAs($this->user('mahasiswa4@gmail.com'))->get(route('documents.download', $version))->assertForbidden();
        $this->actingAs($this->user('dosen@gmail.com'))->get(route('documents.download', $version))->assertOk();
        $this->actingAs($this->user('dosen5@gmail.com'))->get(route('documents.download', $version))->assertForbidden();

        // Tipe file tidak valid ditolak
        $this->actingAs($student)->post(route('mahasiswa.documents.store', $milestone), ['file' => UploadedFile::fake()->create('x.exe', 10), 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors('file');

        // Tipe file mengikuti pengaturan admin per milestone
        $milestone->update(['allowed_types' => ['pdf']]);
        $this->actingAs($student)->post(route('mahasiswa.documents.store', $milestone), ['file' => UploadedFile::fake()->create('slide.pptx', 10), 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasErrors(['file' => 'Format file harus PDF.']);
    }

    public function test_drive_sync_updates_same_file_and_records_failures(): void
    {
        config(['capstone.drive.enabled' => true]);
        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $fake = new FakeDrive;
        $this->app->instance(DriveGateway::class, $fake);

        $student = $this->user('mahasiswa3@gmail.com');
        $milestone = $team->period->documentMilestone('proposal');

        // Folder Drive diisi per milestone dokumen di halaman periode: tautan folder maupun ID diterima
        $admin = $this->user('admin@gmail.com');
        $period = $team->period;
        $identity = ['name' => $period->name, 'academic_year' => $period->academic_year, 'semester' => $period->semester, 'prodi_name' => $period->prodi_name,
            'starts_on' => $period->starts_on->toDateString(), 'ends_on' => $period->ends_on->toDateString(), 'min_approved_logbooks' => $period->min_approved_logbooks];
        $rule = $period->registrationRule()->with('courses', 'specializations')->first();
        $identity['rule'] = ['min_sks' => $rule->min_sks, 'min_members' => $rule->min_members, 'max_members' => $rule->max_members,
            'require_distinct_region' => $rule->require_distinct_region ? '1' : '0', 'require_distinct_specialization' => $rule->require_distinct_specialization ? '1' : '0',
            'courses' => $rule->courses->map(fn ($c) => $c->code.' - '.$c->name)->implode(PHP_EOL), 'specializations' => $rule->specializations->pluck('name')->implode(PHP_EOL)];
        $this->actingAs($admin)->get(route('admin.periods.edit', $period))->assertOk()->assertSee('drive_folders['.$milestone->id.']', false);
        $this->actingAs($admin)->put(route('admin.periods.update', $period), $identity + ['drive_folders' => [$milestone->id => 'bukan tautan']])
            ->assertSessionHasErrors('drive_folders.'.$milestone->id);
        $this->actingAs($admin)->put(route('admin.periods.update', $period), $identity + ['drive_folders' => [$milestone->id => 'https://drive.google.com/drive/folders/proposalFolder01?usp=sharing']])
            ->assertSessionHasNoErrors();
        $this->assertSame('proposalFolder01', $milestone->fresh()->drive_folder_id);
        $milestone->refresh();

        // Mulai dari milestone tanpa pengumpulan agar dokumen tim baru dibuat
        Document::query()->where('team_id', $team->id)->where('milestone_id', $milestone->id)->delete();
        $this->actingAs($student)->post(route('mahasiswa.documents.store', $milestone), ['file' => UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'), 'idempotency_key' => (string) Str::uuid()]);
        $document = Document::query()->where('team_id', $team->id)->where('milestone_id', $milestone->id)->sole();
        $this->assertSame('file-1', $document->drive_file_id);
        $this->assertSame('berhasil', $document->versions()->first()->sync_status);
        $this->assertSame(['create', 'share'], array_column($fake->calls, 0));


        // Revisi memperbarui file yang sama (F37)
        $this->actingAs($student)->post(route('mahasiswa.documents.store', $milestone), ['file' => UploadedFile::fake()->create('b.pdf', 50, 'application/pdf'), 'idempotency_key' => (string) Str::uuid()]);
        $this->assertSame('update', end($fake->calls)[0]);
        $this->assertSame('file-1', $document->fresh()->drive_file_id);
        $this->assertSame(2, $document->fresh()->current_revision);

        // Kegagalan: versi lama tetap tercatat sebagai versi terkini (F40)
        $fake->fail = true;
        try {
            $this->actingAs($student)->post(route('mahasiswa.documents.store', $milestone), ['file' => UploadedFile::fake()->create('c.pdf', 50, 'application/pdf'), 'idempotency_key' => (string) Str::uuid()]);
        } catch (RuntimeException) {
            // job sync berjalan sinkron di test; exception diteruskan untuk retry antrean
        }
        $document->refresh();
        $this->assertSame(2, $document->current_revision);
        $this->assertSame('gagal', DocumentVersion::query()->where('document_id', $document->id)->where('revision_no', 3)->value('sync_status'));
        $this->assertDatabaseHas('drive_operations', ['document_id' => $document->id, 'status' => 'gagal']);

        // Mahasiswa tidak dapat menghapus pengumpulan; tombolnya kini "Unggah file ulang"
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('mahasiswa.documents.destroy'));
        $this->actingAs($student)->delete('/mahasiswa/dokumen/'.$document->id)->assertStatus(405);
        $fake->fail = false;
        $this->actingAs($student)->get(route('mahasiswa.documents.index'))->assertOk()
            ->assertSee('Unggah file ulang')->assertDontSee('data-confirm-label="Hapus"', false);
        $this->assertSame('aktif', $document->fresh()->state);

        // Hapus oleh admin (permanen); gagal Drive → dokumen tidak berubah (F38)
        $admin = User::query()->where('role', 'admin')->first();
        $fake->fail = true;
        $this->actingAs($admin)->delete(route('admin.documents.destroy', $document))->assertSessionHasErrors('document');
        $this->assertModelExists($document);
        $fake->fail = false;
        $this->actingAs($admin)->delete(route('admin.documents.destroy', $document))->assertSessionHasNoErrors();
        $this->assertModelMissing($document);
        $this->assertSame('delete', end($fake->calls)[0]);

        // Gagal berbagi akses ke pemilik (mis. email bukan akun Google) tidak menggagalkan sinkronisasi
        $fake->failShare = true;
        $this->actingAs($student)->post(route('mahasiswa.documents.store', $milestone), ['file' => UploadedFile::fake()->create('a.pdf', 50, 'application/pdf'), 'idempotency_key' => (string) Str::uuid()]);
        $shared = Document::query()->where('team_id', $team->id)->where('milestone_id', $milestone->id)->where('state', 'aktif')->sole();
        $this->assertSame('berhasil', $shared->versions()->first()->sync_status);
        $this->assertTrue(\App\Models\DriveOperation::query()->where('document_id', $shared->id)->where('action', 'share')->where('status', 'gagal')->exists());
        $fake->failShare = false;
    }

    public function test_reminder_api_dedupes_and_stops_when_done(): void
    {
        config(['capstone.automation.token' => 'secret']);
        $period = Period::active();
        $period->assessmentWindows()->where('component', 'proposal')->update(['opens_at' => now()->subDays(3), 'due_at' => now()->addHours(24)]);

        $this->postJson('/api/automation/reminders/claim')->assertUnauthorized();

        $first = $this->postJson('/api/automation/reminders/claim', [], ['X-Automation-Token' => 'secret'])->assertOk()->json('data');
        $proposal = collect($first)->where('job_type', 'penilaian')->values();
        // Tim Lintang (pembimbing dsn3) belum dinilai proposal → email + WA
        $this->assertCount(2, $proposal);
        $this->assertStringContainsString('Tim Lintang', $proposal[0]['message']);
        $this->assertNotEmpty($proposal[0]['remaining_text']);

        // Pemeriksaan berikutnya tidak menggandakan (F54)
        $second = $this->postJson('/api/automation/reminders/claim', [], ['X-Automation-Token' => 'secret'])->json('data');
        $this->assertCount(0, collect($second)->where('job_type', 'penilaian'));

        $this->postJson("/api/automation/reminders/{$proposal[0]['id']}/report", ['status' => 'terkirim'], ['X-Automation-Token' => 'secret'])->assertOk();
        $this->postJson("/api/automation/reminders/{$proposal[1]['id']}/report", ['status' => 'gagal', 'error' => 'Fonnte timeout'], ['X-Automation-Token' => 'secret'])->assertOk();

        // Dosen menyelesaikan penilaian → pengingat ulang yang gagal dibatalkan, tidak dikirim (F53)
        $team = Team::query()->where('name', 'Tim Lintang')->first();
        Assessment::create(['team_id' => $team->id, 'assessor_id' => $this->user('dosen3@gmail.com')->id, 'component' => 'proposal', 'assessor_role' => 'pembimbing', 'status' => 'final', 'submitted_at' => now()]);
        $third = $this->postJson('/api/automation/reminders/claim', [], ['X-Automation-Token' => 'secret'])->json('data');
        $this->assertCount(0, collect($third)->where('job_type', 'penilaian'));
        $this->assertSame('dibatalkan', ReminderDispatch::find($proposal[1]['id'])->status);
        $this->assertSame('terkirim', ReminderDispatch::find($proposal[0]['id'])->status);
    }

    public function test_manual_reminder_requires_contact(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $dsn4 = $this->user('dosen4@gmail.com'); // tanpa nomor WA
        $team = Team::query()->where('name', 'Tim Bentala')->first();
        TeamLecturer::create(['team_id' => $team->id, 'lecturer_id' => $dsn4->id, 'role' => 'penguji', 'source' => 'admin']);

        $this->actingAs($admin)->post(route('admin.monitoring.remind'), ['lecturer_ids' => [$dsn4->id], 'channels' => ['email', 'whatsapp']])->assertSessionHasNoErrors();
        $this->assertSame('antre', ReminderDispatch::query()->where('lecturer_id', $dsn4->id)->where('channel', 'email')->value('status'));
        $this->assertSame('gagal', ReminderDispatch::query()->where('lecturer_id', $dsn4->id)->where('channel', 'whatsapp')->value('status'));
    }

    public function test_qr_resolution_rules(): void
    {
        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $token = $team->activeQrToken()->token;
        $supervisor = $this->user('dosen@gmail.com');

        // Belum login → login dulu (F59)
        $this->get(route('qr.resolve', $token))->assertRedirect(route('login'));

        // Token tak dikenal / format salah (F32)
        $this->actingAs($supervisor)->get(route('qr.resolve', str_repeat('x', 48)))->assertNotFound();

        // Pembimbing pada jendela proposal yang terbuka → langsung ke form (F58)
        $this->actingAs($supervisor)->get(route('qr.resolve', $token))
            ->assertRedirect(route('dosen.assessments.edit', [$team, 'proposal', 'via' => 'qr']));

        // Bukan dosen tim → ditolak walau memegang tautan
        $this->actingAs($this->user('dosen5@gmail.com'))->get(route('qr.resolve', $token))->assertForbidden();

        // Penguji tanpa tahap aktif miliknya
        $this->actingAs($this->user('dosen3@gmail.com'))->get(route('qr.resolve', $token))->assertStatus(409);

        // QR dibuat ulang → yang lama tidak berlaku
        app(\App\Services\QrService::class)->regenerate($team);
        $this->actingAs($supervisor)->get(route('qr.resolve', $token))->assertStatus(410);
    }

    public function test_admin_plots_teams_per_lecturer(): void
    {
        $admin = $this->user('admin@gmail.com');
        $lecturer = $this->user('dosen4@gmail.com');
        $team = Team::query()->where('name', 'Tim Lintang')->firstOrFail();
        $other = Team::query()->where('name', 'Tim Arunika')->firstOrFail();
        $session = [Period::WORKSPACE_KEY => $team->period_id];

        // Tab Plotting Dosen: ringkasan flag dan tombol "Detail Plottingan" per dosen
        $this->actingAs($admin)->withSession($session)->get(route('admin.plotting.index', ['tab' => 'dosen']))->assertOk()
            ->assertSee('Detail Plottingan')->assertSee('Dosen status bimbing/uji UNDER')->assertSee('Tambah tim bimbingan');

        // Tambah dari Detail Plottingan: boleh berbeda peminatan (mis. dosen peminatan tim sudah penuh),
        // menggantikan pembimbing sebelumnya, lalu kembali ke tab Plotting Dosen dengan modal dosen terbuka
        $add = fn (Team $t, string $role) => $this->actingAs($admin)->withSession($session)
            ->post(route('admin.plotting.team', $t), ['role' => $role, 'lecturer_id' => $lecturer->id, 'return' => 'dosen']);
        $add($team, 'pembimbing')->assertSessionHasNoErrors()->assertRedirect(route('admin.plotting.index', ['tab' => 'dosen']))->assertSessionHas('plot_lecturer', $lecturer->id);
        $add($other, 'penguji')->assertSessionHasNoErrors();
        $this->assertSame($lecturer->id, $team->supervisor()->first()->lecturer_id);
        $this->assertSame($lecturer->id, $other->examiner()->first()->lecturer_id);

        // Satu tim tidak boleh dibimbing dan diuji dosen yang sama
        $add($team, 'penguji')->assertSessionHasErrors('lecturer_id')->assertSessionHas('plot_lecturer', $lecturer->id);

        // Lepas
        $this->actingAs($admin)->withSession($session)->delete(route('admin.plotting.destroy', $other->examiner()->first()), ['return' => 'dosen'])
            ->assertRedirect(route('admin.plotting.index', ['tab' => 'dosen']));
        $this->assertNull($other->examiner()->first());
        $this->assertSame($lecturer->id, $team->supervisor()->first()->lecturer_id);
    }

    public function test_admin_plots_lecturer_per_team_by_specialization(): void
    {
        $admin = $this->user('admin@gmail.com');
        $team = Team::query()->where('name', 'Tim Lintang')->firstOrFail();
        $session = [Period::WORKSPACE_KEY => $team->period_id];
        $specializations = $team->members->pluck('user.specialization')->filter()->unique();
        $matching = User::query()->lecturers()->whereHas('lecturerProfile', fn ($q) => $q->whereIn('specialization', $specializations))
            ->where('id', '!=', $team->supervisor()->first()?->lecturer_id)->firstOrFail();

        // Tab bawaan: daftar tim dengan kolom peminatan & dosen, tombol Plot dosen / Edit / Hapus
        $this->actingAs($admin)->withSession($session)->get(route('admin.plotting.index'))->assertOk()
            ->assertSee('Plotting Tim Mahasiswa')->assertSee('Dosen Pembimbing')->assertSee('Dosen Penguji')
            ->assertSee('Plot dosen')->assertSee($specializations->first())->assertSee('Topik Mandiri');

        // Tim yang topiknya belum final (masih menunggu) tidak tampil dan tidak dapat diplot
        $pendingTopic = Team::query()->where('status', 'diterima')->whereNull('final_topic_id')->firstOrFail();
        $this->actingAs($admin)->withSession($session)->get(route('admin.plotting.index'))->assertDontSee($pendingTopic->name);
        $this->actingAs($admin)->withSession($session)->post(route('admin.plotting.team', $pendingTopic), ['role' => 'pembimbing', 'lecturer_id' => $matching->id])
            ->assertNotFound();

        // Plot dosen sesuai peminatan tim → kembali ke tab Plotting Tim Mahasiswa
        $this->actingAs($admin)->withSession($session)->post(route('admin.plotting.team', $team), ['role' => 'penguji', 'lecturer_id' => $matching->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.plotting.index'));
        $assignment = $team->examiner()->first();
        $this->assertSame($matching->id, $assignment->lecturer_id);

        // Hapus = lepas dosen dari peran itu
        $this->actingAs($admin)->withSession($session)->delete(route('admin.plotting.destroy', $assignment))->assertSessionHasNoErrors();
        $this->assertNull($team->examiner()->first());
    }

    public function test_support_files_are_visible_only_to_chosen_roles(): void
    {
        $admin = $this->user('admin@gmail.com');
        $student = $this->user('mahasiswa@gmail.com');
        $lecturer = $this->user('dosen@gmail.com');
        $upload = fn (array $roles) => ['title' => 'Rubrik dosen', 'category' => 'Panduan', 'visible_to' => $roles,
            'file' => UploadedFile::fake()->create('rubrik.pdf', 50, 'application/pdf')];

        $this->actingAs($admin)->get(route('admin.files.index'))->assertOk()
            ->assertSee('Tambah File')->assertSee('Role akses melihat')->assertSee('Dapat dilihat:')->assertSee('Unduh')->assertSee('Hapus');

        // Role akses wajib dipilih; hanya dosen / mahasiswa
        $this->actingAs($admin)->post(route('admin.files.store'), $upload([]))->assertSessionHasErrors('visible_to');
        $this->actingAs($admin)->post(route('admin.files.store'), $upload(['admin']))->assertSessionHasErrors('visible_to.0');
        $this->actingAs($admin)->post(route('admin.files.store'), $upload(['dosen']))->assertSessionHasNoErrors();
        $file = \App\Models\SupportFile::query()->where('title', 'Rubrik dosen')->firstOrFail();
        $this->assertSame(['dosen'], $file->visible_to);

        // Dosen melihat & mengunduh; mahasiswa tidak melihat di daftar dan ditolak bila membuka tautannya
        $this->actingAs($lecturer)->get(route('files.index'))->assertOk()->assertSee('Rubrik dosen');
        $this->actingAs($lecturer)->get(route('files.download', $file))->assertOk();
        $this->actingAs($student)->get(route('files.index'))->assertOk()->assertDontSee('Rubrik dosen');
        $this->actingAs($student)->get(route('files.download', $file))->assertForbidden();
        $this->actingAs($student)->get(route('files.show', $file))->assertForbidden();
        $this->actingAs($admin)->get(route('files.show', $file))->assertOk();

        // Ubah: role akses dibuka juga untuk mahasiswa (file lama tetap dipakai bila tidak diganti)
        $this->actingAs($admin)->put(route('admin.files.update', $file), ['title' => 'Rubrik bersama', 'category' => 'Panduan', 'visible_to' => []])
            ->assertSessionHasErrors('visible_to');
        $this->actingAs($admin)->put(route('admin.files.update', $file), ['title' => 'Rubrik bersama', 'category' => 'Panduan', 'visible_to' => ['dosen', 'mahasiswa']])
            ->assertSessionHasNoErrors();
        $this->assertSame(['Rubrik bersama', 'rubrik.pdf'], [$file->fresh()->title, $file->fresh()->original_name]);
        $this->actingAs($student)->get(route('files.download', $file))->assertOk();

        $this->actingAs($admin)->delete(route('admin.files.destroy', $file))->assertSessionHasNoErrors();
        $this->assertModelMissing($file);
    }

    public function test_load_balancing_flags(): void
    {
        $service = app(LoadBalanceService::class);
        // Minimum hanya untuk flag UNDER; maksimum = kuota, sisa berkurang tiap tim yang diplot
        $quota = ['min' => 2, 'max' => 5];
        $this->assertSame('UNDER', $service->flag(1, $quota));
        $this->assertSame('OK', $service->flag(5, $quota));
        $this->assertSame('OVER', $service->flag(6, $quota));
        $this->assertSame(3, $service->remaining(2, $quota));
        $this->assertSame(0, $service->remaining(6, $quota));
        $this->assertTrue($service->isAvailable(4, $quota));
        $this->assertFalse($service->isAvailable(5, $quota));
        // Kuota 0 (hasil Reset) = tidak menerima tim
        $this->assertFalse($service->isAvailable(0, ['min' => 0, 'max' => 0]));
    }

    public function test_student_import_with_nim_and_specialization_only_updates_existing_student(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $student = $this->user('mahasiswa@gmail.com');
        $student->forceFill(['specialization' => null])->save();
        [$name, $email, $phone] = [$student->name, $student->email, $student->phone];
        $before = User::query()->count();

        // NIM terdaftar: cukup nim + peminatan; NIM baru tetap wajib nama & email
        $csv = "nim,nama,email,no_wa,sks_lulus,mk_lulus,daerah_asal,peminatan
"
            ."{$student->nim},,,,,,,Enterprise Data Management
"
            ."9999999999,,,,,,,Enterprise Data Management
";
        $this->actingAs($admin)->post(route('admin.imports.preview', 'mahasiswa'), ['file' => UploadedFile::fake()->createWithContent('mhs.csv', $csv)]);
        $this->actingAs($admin)->get(route('admin.imports.show', 'mahasiswa'))->assertOk()
            ->assertSee('Kolom nama wajib diisi.')->assertSee('Kolom email wajib diisi.');

        $token = Cache::get("import:{$admin->id}:mahasiswa")['token'];
        $this->actingAs($admin)->post(route('admin.imports.commit', 'mahasiswa'), ['token' => $token])->assertSessionHasNoErrors();

        // Hanya peminatan yang berubah; tidak ada akun baru
        $student->refresh();
        $this->assertSame('Enterprise Data Management', $student->specialization);
        $this->assertSame([$name, $email, $phone], [$student->name, $student->email, $student->phone]);
        $this->assertSame($before, User::query()->count());
    }

    public function test_admin_connects_google_drive_with_oauth(): void
    {
        config(['capstone.drive.auth' => 'oauth', 'capstone.drive.oauth.client_id' => 'cid', 'capstone.drive.oauth.client_secret' => 'secret']);
        Storage::fake('local');
        Cache::flush();
        $admin = User::query()->where('role', 'admin')->first();
        $idToken = 'x.'.rtrim(strtr(base64_encode(json_encode(['email' => 'pribadi@gmail.com'])), '+/', '-_'), '=').'.y';
        \Illuminate\Support\Facades\Http::fake([
            'oauth2.googleapis.com/token' => \Illuminate\Support\Facades\Http::response(['access_token' => 'akses-1', 'refresh_token' => 'refresh-1',
                'expires_in' => 3599, 'scope' => 'openid email https://www.googleapis.com/auth/drive', 'id_token' => $idToken]),
            'oauth2.googleapis.com/revoke' => \Illuminate\Support\Facades\Http::response([]),
        ]);

        // Belum terhubung → tombol Hubungkan; mengarah ke halaman persetujuan Google dengan state
        $this->actingAs($admin)->get(route('admin.documents.index'))->assertOk()->assertSee('Hubungkan akun Google')->assertSee(route('admin.drive.callback'));
        $redirect = $this->actingAs($admin)->get(route('admin.drive.connect'))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $redirect);
        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
        $this->assertSame(['cid', 'offline', 'consent'], [$query['client_id'], $query['access_type'], $query['prompt']]);

        // state salah ditolak; state benar → token tersimpan terenkripsi, email akun tampil
        $this->actingAs($admin)->withSession(['gdrive_oauth_state' => 'abc'])->get(route('admin.drive.callback', ['state' => 'salah', 'code' => 'k']))
            ->assertSessionHasErrors('drive');
        $this->actingAs($admin)->withSession(['gdrive_oauth_state' => 'abc'])->get(route('admin.drive.callback', ['state' => 'abc', 'code' => 'k']))
            ->assertRedirect(route('admin.documents.index'))->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('refresh-1', Storage::disk('local')->get('google-drive-oauth.json'));
        $oauth = app(\App\Services\Drive\DriveOAuth::class);
        $this->assertSame('pribadi@gmail.com', $oauth->status()['email']);
        $this->assertSame('akses-1', $oauth->accessToken());
        $this->actingAs($admin)->get(route('admin.documents.index'))->assertSee('pribadi@gmail.com')->assertSee('Hubungkan ulang');

        // Putuskan → token dihapus
        $this->actingAs($admin)->delete(route('admin.drive.disconnect'))->assertRedirect(route('admin.documents.index'));
        $this->assertNull($oauth->status());
    }

    public function test_import_preview_flags_bad_rows(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $csv = "nip,nama,kode_dosen,email,no_wa,peminatan\n"
            ."1,Dosen Baru,DBR,baru@capstone.test,0811,Data\n"
            ."2,Duplikat,RWL,lain@capstone.test,0812,Data\n"
            ."3,Email Salah,XYZ,bukan-email,0813,Data\n";
        $file = UploadedFile::fake()->createWithContent('dosen.csv', $csv);

        $this->actingAs($admin)->post(route('admin.imports.preview', 'dosen'), ['file' => $file])->assertRedirect(route('admin.imports.show', 'dosen'));
        $page = $this->actingAs($admin)->get(route('admin.imports.show', 'dosen'))->assertOk();
        $page->assertSee('Kode dosen sudah dipakai dosen lain.')->assertSee('Format email tidak valid.');

        $token = Cache::get("import:{$admin->id}:dosen")['token'];
        $this->actingAs($admin)->post(route('admin.imports.commit', 'dosen'), ['token' => $token])->assertSessionHasNoErrors();
        $new = User::query()->where('email', 'baru@capstone.test')->first();
        $this->assertSame('DBR', $new->lecturerProfile->lecturer_code);
        $this->assertNull(User::query()->where('name', 'Email Salah')->first());

        // Dosen hasil impor hanya berhak akses "Dosen"
        $this->assertSame(['Dosen'], $new->lecturerProfile->accessLabels());

        // Diplot sebagai penguji (tanpa dicentang lebih dulu) → hak akses Penguji tercentang otomatis
        $team = Team::query()->where('name', 'Tim Lintang')->first();
        $this->actingAs($admin)->withSession([Period::WORKSPACE_KEY => $team->period_id])
            ->post(route('admin.plotting.team', $team), ['role' => 'penguji', 'lecturer_id' => $new->id])->assertSessionHasNoErrors();
        $this->assertSame(['Dosen', 'Penguji'], $new->lecturerProfile()->first()->accessLabels());

        // Admin tetap dapat mengubah hak akses lewat centang
        $profile = $new->lecturerProfile()->first();
        $this->actingAs($admin)->put(route('admin.lecturers.update', $new), [
            'name' => $new->name, 'email' => $new->email, 'phone' => '08110000000', 'lecturer_code' => $profile->lecturer_code,
            'nip' => '1', 'specialization' => 'Data', 'can_supervise' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame(['Dosen', 'Pembimbing'], $new->lecturerProfile()->first()->accessLabels());
    }
}

/** Drive palsu untuk test sinkronisasi. */
class FakeDrive implements DriveGateway
{
    public array $calls = [];

    public bool $fail = false;

    public bool $failShare = false;

    private int $seq = 0;

    private function guard(): void
    {
        if ($this->fail) {
            throw new RuntimeException('Drive tidak tersedia');
        }
    }

    public function createFolder(string $name, ?string $parentId): string
    {
        $this->guard();

        return 'folder-'.md5($name);
    }

    public function upload(string $folderId, string $name, string $mime, string $localPath): string
    {
        $this->guard();
        $this->calls[] = ['create', $name];

        return 'file-'.++$this->seq;
    }

    public function update(string $fileId, string $name, string $mime, string $localPath): string
    {
        $this->guard();
        $this->calls[] = ['update', $fileId];

        return $fileId;
    }

    public function trash(string $fileId): void
    {
        $this->guard();
        $this->calls[] = ['trash', $fileId];
    }

    public function delete(string $fileId): void
    {
        $this->guard();
        $this->calls[] = ['delete', $fileId];
    }

    public function shareWith(string $fileId, string $email): void
    {
        $this->guard();
        if ($this->failShare) {
            throw new RuntimeException('Email bukan akun Google');
        }
        $this->calls[] = ['share', $email];
    }
}
