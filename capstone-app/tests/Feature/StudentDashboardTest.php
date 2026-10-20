<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Logbook;
use App\Models\User;
use App\Support\Format;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** F19 — kartu "To Do" dan status di dashboard mahasiswa, per kondisi mahasiswa. */
class StudentDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
    }

    private function dashboardAs(string $email)
    {
        return $this->actingAs(User::query()->where('email', $email)->firstOrFail())->get(route('mahasiswa.dashboard'))->assertOk();
    }

    public function test_ineligible_student_sees_prerequisites_instead_of_form_team(): void
    {
        // Mira: 98 SKS, belum lulus KP — TeamController menolak pembentukan tim.
        $this->dashboardAs('mahasiswa13@gmail.com')
            ->assertSee('Prasyarat Capstone belum terpenuhi')
            ->assertDontSee('Bentuk tim Capstone')
            ->assertDontSee('Dokumen milestone');
    }

    public function test_eligible_student_without_team_is_asked_to_form_one(): void
    {
        $this->dashboardAs('mahasiswa14@gmail.com')
            ->assertSee('Bentuk tim Capstone')
            ->assertDontSee('Dokumen milestone')
            ->assertDontSee('Isi log');
    }

    public function test_accepted_team_with_topic_is_asked_to_submit_open_document(): void
    {
        // Tim Lintang: diterima, topik final ada, proposal belum dikumpulkan.
        $this->dashboardAs('mahasiswa4@gmail.com')->assertSee('Kumpulkan Proposal');
    }

    public function test_team_awaiting_review_sees_locked_uploads_not_upload_action(): void
    {
        // Tim Samudra: registrasi menunggu — unggah diblokir oleh Team::documentUploadBlocker().
        $this->dashboardAs('mahasiswa6@gmail.com')
            ->assertSee('Registrasi tim sedang ditinjau admin.')
            ->assertViewHas('uploadBlocker', fn ($blocker) => $blocker !== null)
            ->assertDontSee('Kumpulkan Proposal');
    }

    public function test_team_with_everything_submitted_has_no_urgent_action_and_skips_finished_stages(): void
    {
        // Tim Arunika: proposal sudah dikumpulkan, registrasi diterima, topik final ada.
        $this->dashboardAs('mahasiswa@gmail.com')
            ->assertDontSee('Kumpulkan Proposal')
            ->assertViewHas('actions', fn ($actions) => $actions->isEmpty())
            ->assertViewHas('deadline', fn ($deadline) => $deadline['milestone']->name === 'Laporan progress');
    }

    public function test_timeline_stages_follow_team_progress(): void
    {
        // Tim Arunika: registrasi, topik, dan proposal sudah dilalui — tahap berikutnya laporan progress.
        $stages = $this->dashboardAs('mahasiswa@gmail.com')->viewData('stages')->pluck('state', 'name');
        $this->assertSame(['done', 'done', 'done', 'current', 'upcoming', 'upcoming'], $stages->values()->all());

        // Tim Lintang: proposal belum dikumpulkan dan jendelanya masih terbuka.
        $stages = $this->dashboardAs('mahasiswa4@gmail.com')->viewData('stages')->pluck('state', 'name');
        $this->assertSame('current', $stages['Proposal']);
        $this->assertSame('upcoming', $stages['Laporan Progress']);
    }

    public function test_document_and_logbook_revisions_become_actions(): void
    {
        $student = User::query()->where('email', 'mahasiswa@gmail.com')->first();
        $team = $student->teams()->first();
        Document::query()->where('team_id', $team->id)->update(['review_status' => 'revisi', 'feedback' => 'Perbaiki bab 2.']);
        Logbook::query()->where('team_id', $team->id)->limit(1)->update(['status' => 'revisi']);

        $this->dashboardAs('mahasiswa@gmail.com')
            ->assertSee('Revisi Proposal')
            ->assertSee('Perbaiki bab 2.')
            ->assertSee('Revisi 1 log bimbingan');
    }

    public function test_dashboard_query_count_stays_bounded(): void
    {
        $student = User::query()->where('email', 'mahasiswa@gmail.com')->first();
        DB::enableQueryLog();
        $this->actingAs($student)->get(route('mahasiswa.dashboard'))->assertOk();

        // Sebelum redesign: 21 query. Batas ini menjaga agar tidak muncul N+1 di kemudian hari.
        $this->assertLessThanOrEqual(20, count(DB::getQueryLog()));
    }

    public function test_due_in_formats_calendar_days(): void
    {
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));

        $this->assertSame('Hari ini', Format::dueIn(Carbon::parse('2026-10-20 23:59')));
        $this->assertSame('Besok', Format::dueIn(Carbon::parse('2026-10-21 08:00')));
        $this->assertSame('5 hari lagi', Format::dueIn(Carbon::parse('2026-10-25 23:59')));
        $this->assertSame('Tenggat terlewat hari ini', Format::dueIn(Carbon::parse('2026-10-20 08:00')));
        $this->assertSame('Terlambat 2 hari', Format::dueIn(Carbon::parse('2026-10-18 23:59')));
    }
}
