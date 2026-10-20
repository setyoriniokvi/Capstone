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
        $leader = $this->user('mahasiswa14@gmail.com');           // TI, belum punya tim
        $member = User::query()->where('nim', '102022300013')->first(); // s13 — SKS kurang
        $admin = User::query()->where('role', 'admin')->first();

        $other = $this->user('mahasiswa10@gmail.com');
        $other->memberships()->delete();
        $khs = fn () => UploadedFile::fake()->create('khs.pdf', 100, 'application/pdf');
        $form = fn (User $mate, array $extra = []) => [
            'name' => 'Tim Uji',
            'leader_region' => 'Bandung, Jawa Barat',
            'leader_khs' => $khs(),
            'members' => [['user_id' => $mate->id, 'origin_region' => 'Medan, Sumatera Utara', 'khs' => $khs()]],
        ] + $extra;

        // F21 — anggota yang tidak memenuhi prasyarat ditolak sistem
        $this->actingAs($leader)->post(route('mahasiswa.team.store'), $form($member))->assertSessionHasErrors('members.0.user_id');
        $this->assertNull($leader->teamFor(Period::active()));

        // KHS anggota wajib
        $incomplete = $form($other);
        unset($incomplete['members'][0]['khs']);
        $this->actingAs($leader)->post(route('mahasiswa.team.store'), $incomplete)->assertSessionHasErrors('members.0.khs');

        // Registrasi lengkap langsung diajukan ke admin
        $this->actingAs($leader)->post(route('mahasiswa.team.store'), $form($other))->assertSessionHasNoErrors();
        $team = $leader->teamFor(Period::active());
        $this->assertSame('menunggu', $team->status);
        $this->assertStringStartsWith('KP-TI-', $team->code);
        $this->assertSame($team->id, $other->teamFor(Period::active())?->id);
        $this->assertSame('Medan, Sumatera Utara', $other->fresh()->origin_region);
        $this->assertSame(100, $team->screening_score);

        // Sesama anggota dapat melihat KHS anggota lain
        $otherMember = $team->members()->where('user_id', $other->id)->first();
        $this->actingAs($leader)->get(route('attachments.download', [$team, $otherMember, 'khs']))->assertOk();

        // F6 — penolakan wajib catatan, lalu diterima
        $this->actingAs($admin)->post(route('admin.teams.decide', $team), ['decision' => 'reject'])->assertSessionHasErrors('notes');
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

        // Melamar topik dosen sebelum topik mandiri diterima → ditolak (flowchart mahasiswa)
        $bankTopic = Topic::query()->published()->where('title', 'like', 'Dashboard%')->first();
        $this->actingAs($leader)->post(route('mahasiswa.bank.apply', $bankTopic), $portfolio)->assertSessionHasErrors('topic');

        $this->actingAs($admin)->post(route('admin.topics.decide', $topic), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame($topic->id, $team->fresh()->final_topic_id);

        // F22 + F43 — lamar, dosen menerima → dosen otomatis pembimbing & topik final tergantikan (F8)
        $this->actingAs($leader)->post(route('mahasiswa.bank.apply', $bankTopic), $portfolio)->assertSessionHasNoErrors();
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
            'object' => 'PT Contoh Distribusi', 'prodi_code' => 'TI',
        ];

        // Peminatan di luar daftar aturan registrasi periode ditolak
        $this->actingAs($lecturer)->post(route('dosen.topics.store'), $form + ['competencies' => 'Peminatan Fiktif'])->assertSessionHasErrors('competencies');

        $this->actingAs($lecturer)->post(route('dosen.topics.store'), $form + ['competencies' => 'Production Systems'])->assertSessionHasNoErrors();
        $topic = Topic::query()->where('title', $form['title'])->firstOrFail();
        $this->assertSame('Production Systems', $topic->competencies);
        $this->assertSame('PT Contoh Distribusi', $topic->object);
    }

    public function test_lecturer_dashboard_greets_with_lecturer_code_and_roles(): void
    {
        $this->actingAs($this->user('dosen2@gmail.com'))->get(route('dosen.dashboard'))->assertOk()
            ->assertSee('Selamat datang, Bapak/Ibu HSP.')
            ->assertSee('Pembimbing');
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

        // Halaman Tim Pelamar dosen menampilkan kuota topik & pelamar
        $this->actingAs($this->user('dosen3@gmail.com'))->get(route('dosen.applications.index'))->assertOk()
            ->assertSee('Topik Saya')->assertSee($team->name);

        // Satu diterima → pengajuan lain otomatis dibatalkan dan pengajuan baru ditolak
        $this->actingAs($this->user('dosen3@gmail.com'))->post(route('dosen.applications.decide', $pending), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('dibatalkan', $team->applications()->where('topic_id', $another->id)->value('status'));
        $third = Topic::query()->published()->whereNotIn('id', [$another->id, $pending->topic_id])->firstOrFail();
        $this->actingAs($student)->post(route('mahasiswa.bank.apply', $third), $portfolio)->assertSessionHasErrors('topic');

        // Penolakan boleh tanpa alasan
        $other = TopicApplication::create(['topic_id' => $third->id, 'team_id' => Team::where('code', 'KP-MR-01')->value('id')]);
        $this->actingAs($third->lecturer)->post(route('dosen.applications.decide', $other), ['decision' => 'reject'])->assertSessionHasNoErrors();
        $this->assertSame('ditolak', $other->fresh()->status);
    }

    public function test_quota_full_blocks_application(): void
    {
        $topic = Topic::query()->published()->where('title', 'like', 'Dashboard%')->first();
        $topic->update(['quota' => 1]);
        $t2 = Team::query()->where('code', 'KP-TI-01')->first();
        TopicApplication::create(['topic_id' => $topic->id, 'team_id' => Team::where('code', 'KP-MR-01')->value('id'), 'status' => 'diterima']);
        $t2->applications()->delete();

        $this->actingAs($this->user('mahasiswa4@gmail.com'))->post(route('mahasiswa.bank.apply', $topic), ['portfolio_url' => 'https://drive.google.com/x'])
            ->assertSessionHasErrors(['topic' => 'Kuota topik ini sudah penuh. Pilih topik lain.']);
    }

    public function test_logbook_gate_and_review(): void
    {
        $team = Team::query()->where('code', 'KP-TI-01')->first();
        $student = $this->user('mahasiswa4@gmail.com');
        $lecturer = $this->user('dosen3@gmail.com');

        $this->actingAs($student)->post(route('mahasiswa.logbooks.store'), [
            'session_date' => now()->toDateString(), 'activity' => 'Validasi kebutuhan',
        ])->assertSessionHasNoErrors();
        $log = Logbook::query()->where('team_id', $team->id)->latest('id')->first();
        $this->assertSame(4, $log->session_no);

        // Log baru tampil di kartu Pengajuan Log Bimbingan pembimbing
        $this->actingAs($lecturer)->get(route('dosen.logbooks.index'))->assertOk()
            ->assertSee('Pengajuan Log Bimbingan')->assertSee('Validasi kebutuhan');

        $this->actingAs($lecturer)->get(route('dosen.logbooks.index', ['tab' => 'log']))->assertOk()
            ->assertSee('Jumlah Tim Bimbingan')->assertSee('Validasi kebutuhan')->assertSee('Tanggal bimbingan');

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
        $this->actingAs($lecturer)->put(route('dosen.assessments.update', [$team, 'sidang_pembimbing']), ['action' => 'draft'])
            ->assertSessionHasErrors('gate');
    }

    public function test_assessment_formula_and_recap(): void
    {
        $team = Team::query()->where('code', 'KP-SI-01')->first();
        $calculator = app(ScoreCalculator::class);
        $indicator = $calculator->indicatorsFor('proposal')->firstWhere('code', 'IND-1');

        // (Skor / 5) × Bobot × Faktor — IND-1 bobot proposal 5, skor 4, skema dokumen → 4
        $this->assertEqualsWithDelta(4.0, $calculator->indicatorValue($indicator, 'proposal', 4), 0.001);

        $oral = $calculator->indicators()->firstWhere('code', 'IND-6');
        $this->assertTrue($oral->usesOralFactor());
        // bobot sidang_pembimbing 6, skor 5, faktor 0.5 → 3
        $this->assertEqualsWithDelta(3.0, $calculator->indicatorValue($oral, 'sidang_pembimbing', 5, 0.5), 0.001);

        $result = $calculator->forTeam($team);
        $this->assertFalse($result['complete']);
        $this->assertNotContains('proposal', $result['missing']);
        $this->assertContains('peer_review', $result['missing']);

        // Ekspor penuh diblokir bila belum lengkap; parsial tersedia
        $admin = User::query()->where('role', 'admin')->first();
        $this->actingAs($admin)->get(route('admin.recap.export'))->assertSessionHasErrors('export');
        $this->actingAs($admin)->get(route('admin.recap.export', ['mode' => 'parsial']))->assertOk()->assertDownload();
    }

    public function test_lecturer_only_assesses_own_component(): void
    {
        $team = Team::query()->where('code', 'KP-SI-01')->first();
        // dsn3 adalah penguji KP-SI-01 → tidak boleh menilai proposal (komponen pembimbing)
        $this->actingAs($this->user('dosen3@gmail.com'))->get(route('dosen.assessments.edit', [$team, 'proposal']))->assertForbidden();
        $this->actingAs($this->user('dosen5@gmail.com'))->get(route('dosen.assessments.edit', [$team, 'proposal']))->assertForbidden();
    }

    public function test_peer_review_unlocks_results(): void
    {
        $student = $this->user('mahasiswa@gmail.com');
        $this->actingAs($student)->get(route('mahasiswa.results.index'))->assertSee('Isi peer review terlebih dahulu');

        $team = Team::query()->where('code', 'KP-SI-01')->first();
        $peerWindow = $team->period->milestones()->where('kind', 'peer_review')->first();

        // Di luar jendela peer review → ditolak
        $this->actingAs($student)->post(route('mahasiswa.peer.store'), ['reviewee_id' => 0, 'scores' => []])->assertSessionHasErrors('window');
        $peerWindow->update(['starts_at' => now()->subDay(), 'due_at' => now()->addDay()]);

        // Setiap rekan dinilai terpisah (6 aspek per rekan)
        $scores = array_fill_keys(array_keys(config('capstone.peer_aspects')), 4);
        $mates = $team->activeUsers()->whereKeyNot($student->id)->pluck('users.id');
        $this->actingAs($student)->post(route('mahasiswa.peer.store'), ['reviewee_id' => $mates[0], 'scores' => $scores])->assertSessionHasNoErrors();
        $this->assertSame(6, PeerReview::query()->where('reviewer_id', $student->id)->count());
        $this->actingAs($student)->get(route('mahasiswa.results.index'))->assertSee('Isi peer review terlebih dahulu');

        // Bukan rekan satu tim → ditolak
        $this->actingAs($student)->post(route('mahasiswa.peer.store'), ['reviewee_id' => $this->user('mahasiswa4@gmail.com')->id, 'scores' => $scores])->assertSessionHasErrors('reviewee_id');

        foreach ($mates as $id) {
            $this->actingAs($student)->post(route('mahasiswa.peer.store'), ['reviewee_id' => $id, 'scores' => $scores])->assertSessionHasNoErrors();
        }
        // Overwrite, bukan menambah baris
        $this->assertSame(12, PeerReview::query()->where('reviewer_id', $student->id)->count());

        $this->actingAs($student)->get(route('mahasiswa.results.index'))->assertDontSee('Isi peer review terlebih dahulu');
    }

    public function test_document_upload_is_idempotent_and_team_scoped(): void
    {
        $student = $this->user('mahasiswa2@gmail.com');
        $team = Team::query()->where('code', 'KP-SI-01')->first();
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
        $team = Team::query()->where('code', 'KP-SI-01')->first();
        $team->period->update(['drive_root_folder_id' => 'root-folder']);
        $fake = new FakeDrive;
        $this->app->instance(DriveGateway::class, $fake);

        $student = $this->user('mahasiswa3@gmail.com');
        $milestone = $team->period->documentMilestone('proposal');

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

        // Hapus standar → Trash di Drive; gagal Drive → status DB tidak berubah (F38)
        $this->actingAs($student)->delete(route('mahasiswa.documents.destroy', $document))->assertSessionHasErrors('document');
        $this->assertSame('aktif', $document->fresh()->state);
        $fake->fail = false;
        $this->actingAs($student)->delete(route('mahasiswa.documents.destroy', $document))->assertSessionHasNoErrors();
        $this->assertSame('trash', $document->fresh()->state);
        $this->assertSame('trash', end($fake->calls)[0]);
    }

    public function test_reminder_api_dedupes_and_stops_when_done(): void
    {
        config(['capstone.automation.token' => 'secret']);
        $period = Period::active();
        $period->assessmentWindows()->where('component', 'proposal')->update(['opens_at' => now()->subDays(3), 'due_at' => now()->addHours(24)]);

        $this->postJson('/api/automation/reminders/claim')->assertUnauthorized();

        $first = $this->postJson('/api/automation/reminders/claim', [], ['X-Automation-Token' => 'secret'])->assertOk()->json('data');
        $proposal = collect($first)->where('job_type', 'penilaian')->values();
        // KP-TI-01 (pembimbing dsn3) belum dinilai proposal → email + WA
        $this->assertCount(2, $proposal);
        $this->assertStringContainsString('KP-TI-01', $proposal[0]['message']);
        $this->assertNotEmpty($proposal[0]['remaining_text']);

        // Pemeriksaan berikutnya tidak menggandakan (F54)
        $second = $this->postJson('/api/automation/reminders/claim', [], ['X-Automation-Token' => 'secret'])->json('data');
        $this->assertCount(0, collect($second)->where('job_type', 'penilaian'));

        $this->postJson("/api/automation/reminders/{$proposal[0]['id']}/report", ['status' => 'terkirim'], ['X-Automation-Token' => 'secret'])->assertOk();
        $this->postJson("/api/automation/reminders/{$proposal[1]['id']}/report", ['status' => 'gagal', 'error' => 'Fonnte timeout'], ['X-Automation-Token' => 'secret'])->assertOk();

        // Dosen menyelesaikan penilaian → pengingat ulang yang gagal dibatalkan, tidak dikirim (F53)
        $team = Team::query()->where('code', 'KP-TI-01')->first();
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
        $team = Team::query()->where('code', 'KP-MR-01')->first();
        TeamLecturer::create(['team_id' => $team->id, 'lecturer_id' => $dsn4->id, 'role' => 'penguji', 'source' => 'admin']);

        $this->actingAs($admin)->post(route('admin.monitoring.remind'), ['lecturer_ids' => [$dsn4->id], 'channels' => ['email', 'whatsapp']])->assertSessionHasNoErrors();
        $this->assertSame('antre', ReminderDispatch::query()->where('lecturer_id', $dsn4->id)->where('channel', 'email')->value('status'));
        $this->assertSame('gagal', ReminderDispatch::query()->where('lecturer_id', $dsn4->id)->where('channel', 'whatsapp')->value('status'));
    }

    public function test_qr_resolution_rules(): void
    {
        $team = Team::query()->where('code', 'KP-SI-01')->first();
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
        $this->actingAs(User::query()->where('role', 'admin')->first())->post(route('admin.teams.qr', $team));
        $this->actingAs($supervisor)->get(route('qr.resolve', $token))->assertStatus(410);
    }

    public function test_load_balancing_flags(): void
    {
        $service = app(LoadBalanceService::class);
        $quota = ['default' => 5, 'min' => 2, 'max' => 8];
        $this->assertSame('UNDER', $service->flag(1, $quota));
        $this->assertSame('OK', $service->flag(8, $quota));
        $this->assertSame('OVER', $service->flag(9, $quota));
        $this->assertFalse($service->isAvailable(8, $quota));
        $this->assertTrue($service->isAvailable(7, $quota));
    }

    public function test_import_preview_flags_bad_rows(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $csv = "nip,nama,kode_dosen,email,no_wa,prodi,peminatan,hak_akses\n"
            ."1,Dosen Baru,DBR,baru@capstone.test,0811,SI,Data,pembimbing;penguji\n"
            ."2,Duplikat,RWL,lain@capstone.test,0812,SI,Data,pembimbing\n"
            ."3,Salah Akses,XYZ,xyz@capstone.test,0813,XX,Data,ketua\n";
        $file = UploadedFile::fake()->createWithContent('dosen.csv', $csv);

        $this->actingAs($admin)->post(route('admin.imports.preview', 'dosen'), ['file' => $file])->assertRedirect(route('admin.imports.show', 'dosen'));
        $page = $this->actingAs($admin)->get(route('admin.imports.show', 'dosen'))->assertOk();
        $page->assertSee('Kode dosen sudah dipakai dosen lain.')->assertSee('Prodi XX tidak dikenal.');

        $token = Cache::get("import:{$admin->id}:dosen")['token'];
        $this->actingAs($admin)->post(route('admin.imports.commit', 'dosen'), ['token' => $token])->assertSessionHasNoErrors();
        $new = User::query()->where('email', 'baru@capstone.test')->first();
        $this->assertTrue($new->lecturerProfile->can_supervise);
        $this->assertTrue($new->lecturerProfile->can_examine);
        $this->assertFalse($new->lecturerProfile->can_propose_topic);
        $this->assertNull(User::query()->where('email', 'xyz@capstone.test')->first());
    }
}

/** Drive palsu untuk test sinkronisasi. */
class FakeDrive implements DriveGateway
{
    public array $calls = [];

    public bool $fail = false;

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
        $this->calls[] = ['share', $email];
    }
}
