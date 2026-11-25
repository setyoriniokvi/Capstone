<?php

namespace Tests\Feature;

use App\Models\LecturerProfile;
use App\Models\Period;
use App\Models\TeamLecturer;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Menu sidebar & penilaian dosen mengikuti perannya: pembimbing saja, penguji saja, keduanya, atau belum keduanya. */
class LecturerRoleMenuTest extends TestCase
{
    use RefreshDatabase;

    private Period $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
        $this->period = Period::active();
    }

    /** Atur peran dosen: hak akses profil + penugasan tim di periode demo. */
    private function lecturerWith(string $code, bool $supervise, bool $examine): User
    {
        $profile = LecturerProfile::inPeriod($this->period)->where('lecturer_code', $code)->firstOrFail();
        $profile->update(['can_supervise' => $supervise, 'can_examine' => $examine]);
        $roles = array_keys(array_filter(['pembimbing' => $supervise, 'penguji' => $examine]));
        TeamLecturer::query()->where('lecturer_id', $profile->user_id)->whereNotIn('role', $roles)->delete();

        return $profile->user()->first();
    }

    public function test_supervisor_only_does_not_see_examiner_menu(): void
    {
        $lecturer = $this->lecturerWith('RWL', true, false);
        $this->assertSame(['pembimbing'], $lecturer->lecturerRoles());

        $this->actingAs($lecturer)->get(route('dosen.dashboard'))->assertOk()
            ->assertSee('Tim Bimbingan')->assertSee('Approval Bimbingan')->assertSee('Penilaian')
            ->assertDontSee('Tim Uji')->assertSee('tim bimbingan')->assertDontSee('tim uji');
    }

    public function test_examiner_only_does_not_see_supervisor_menu_or_components(): void
    {
        $lecturer = $this->lecturerWith('MKT', false, true);
        $this->assertSame(['penguji'], $lecturer->lecturerRoles());

        $this->actingAs($lecturer)->get(route('dosen.dashboard'))->assertOk()
            ->assertSee('Tim Uji')->assertSee('Penilaian')
            ->assertDontSee('Tim Bimbingan')->assertDontSee('Approval Bimbingan');
        // Tab penilaian hanya bentuk penilaian yang dinilai penguji
        $page = $this->actingAs($lecturer)->get(route('dosen.assessments.index'))->assertOk();
        foreach (config('capstone.components') as $key => $component) {
            if (! in_array('penguji', $component['roles'] ?? [], true)) {
                $page->assertDontSee(route('dosen.assessments.index', ['komponen' => $key]), false);
            }
        }
    }

    public function test_lecturer_without_role_sees_only_topic_menus(): void
    {
        $lecturer = $this->lecturerWith('SMH', false, false);
        $this->assertSame([], $lecturer->lecturerRoles());

        $this->actingAs($lecturer)->get(route('dosen.dashboard'))->assertOk()
            ->assertSee('Topik Dosen')->assertSee('Approval Pengajuan Topik')
            ->assertDontSee('Tim Bimbingan')->assertDontSee('Tim Uji')->assertDontSee('Approval Bimbingan')
            ->assertDontSee(route('dosen.assessments.index'), false);
    }

    public function test_plotting_as_examiner_reveals_examiner_menu(): void
    {
        $lecturer = $this->lecturerWith('RWL', true, false);
        $team = \App\Models\Team::query()->where('name', 'Tim Lintang')->first();
        $admin = User::query()->where('role', 'admin')->first();
        $this->actingAs($admin)->withSession([Period::WORKSPACE_KEY => $this->period->id])
            ->post(route('admin.plotting.team', $team), ['role' => 'penguji', 'lecturer_id' => $lecturer->id])->assertSessionHasNoErrors();

        $this->assertSame(['pembimbing', 'penguji'], $lecturer->fresh()->lecturerRoles());
        $this->actingAs($lecturer->fresh())->get(route('dosen.dashboard'))->assertOk()->assertSee('Tim Uji')->assertSee('Tim Bimbingan');
    }
}
