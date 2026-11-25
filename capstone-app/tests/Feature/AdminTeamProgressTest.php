<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Detail tim di Monitoring progres (admin): susunan seperti detail registrasi + progres tim + penilaian lihat saja. */
class AdminTeamProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
        $this->admin = User::query()->where('role', 'admin')->first();
        $this->team = Team::query()->where('name', 'Tim Arunika')->firstOrFail();
        $this->withSession([Period::WORKSPACE_KEY => $this->team->period_id]);
    }

    public function test_progress_detail_shows_registration_members_and_progress_sections(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.monitoring.progress', ['tim' => $this->team->id]))->assertOk()
            ->assertSee('Registrasi diterima')->assertSee('Ubah tim')->assertSee('Hapus tim')
            ->assertSee('Lihat KHS')->assertSee('flex-nowrap items-center gap-2', false)
            ->assertSee('Topik dan Objek Final')->assertSee('Sesi Bimbingan')->assertSee('Deliverable')->assertSee('Hasil Penilaian')
            ->assertSee(route('admin.monitoring.assessment', [$this->team, 'proposal']), false)
            ->getContent();
        // Deliverable tanpa tombol Nilai; detail tim yang diminta (?tim=) langsung terbuka
        $this->assertStringNotContainsString(route('dosen.assessments.edit', [$this->team, 'proposal']), $html);
        $this->assertStringContainsString('data-open="true"', $html);
    }

    public function test_admin_views_assessment_read_only(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.monitoring.assessment', [$this->team, 'proposal']))->assertOk()
            ->assertSee('Mode lihat saja')->assertSee('score-sheet', false)
            ->assertDontSee('Submit Final')->assertDontSee('Simpan Draft')->assertDontSee('Simpan feedback')
            ->assertSee(route('admin.monitoring.progress', ['tim' => $this->team->id]), false)
            ->getContent();
        $this->assertStringNotContainsString(route('dosen.assessments.scores', [$this->team, 'proposal']), $html);
        // Semua dropdown interval terkunci
        preg_match_all('/<select id="interval-[^>]*>/', $html, $selects);
        $this->assertNotEmpty($selects[0]);
        foreach ($selects[0] as $select) {
            $this->assertStringContainsString('disabled', $select);
        }

        $this->actingAs($this->admin)->get(route('admin.monitoring.assessment', [$this->team, 'tidak-ada']))->assertNotFound();
        // Dosen tidak dapat membuka halaman admin
        $this->actingAs($this->team->supervisor->lecturer)->get(route('admin.monitoring.assessment', [$this->team, 'proposal']))->assertForbidden();
    }

    public function test_editing_team_from_progress_returns_to_progress_detail(): void
    {
        $members = $this->team->members()->orderByDesc('is_leader')->pluck('user_id')->all();
        $this->actingAs($this->admin)->from(route('admin.monitoring.progress', ['q' => 'Arunika']))
            ->put(route('admin.teams.update', $this->team), ['_team' => (string) $this->team->id, 'name' => 'Tim Arunika Baru', 'members' => $members])
            ->assertRedirect(route('admin.monitoring.progress', ['q' => 'Arunika', 'tim' => $this->team->id]));
        $this->assertSame('Tim Arunika Baru', $this->team->fresh()->name);

        // Dari halaman Registrasi Tim tetap kembali ke Registrasi Tim
        $this->actingAs($this->admin)->from(route('admin.teams.index'))
            ->put(route('admin.teams.update', $this->team), ['_team' => (string) $this->team->id, 'name' => 'Tim Arunika', 'members' => $members])
            ->assertRedirect(route('admin.teams.index', ['tim' => $this->team->id]));
    }
}
