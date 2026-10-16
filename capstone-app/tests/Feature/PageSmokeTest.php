<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Memastikan setiap halaman setiap peran dapat dirender tanpa error. */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk ke Capstone');
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $team = Team::query()->where('code', 'KP-SI-01')->first();
        $period = $team->period;

        foreach ([
            route('admin.dashboard'), route('admin.periods.index'), route('admin.periods.create'), route('admin.periods.edit', $period),
            route('admin.rules.index'), route('admin.lecturers.index'), route('admin.lecturers.create'),
            route('admin.lecturers.edit', User::query()->where('role', 'dosen')->first()),
            route('admin.students.index'), route('admin.students.edit', User::query()->where('role', 'mahasiswa')->first()),
            route('admin.teams.index'), route('admin.teams.show', $team), route('admin.topics.index'), route('admin.topics.index', ['sumber' => 'dosen', 'status' => 'semua']),
            route('admin.topics.recap'), route('admin.quotas.index'), route('admin.plotting.index'),
            route('admin.monitoring.progress'), route('admin.monitoring.assessments'),
            route('admin.clos.index'), route('admin.indicators.index'), route('admin.indicators.create'),
            route('admin.indicators.edit', Indicator::first()), route('admin.rubrics.index'), route('admin.rubrics.edit', Indicator::first()),
            route('admin.oral.index'), route('admin.files.index'), route('admin.recap.index'), route('admin.reminders.index'),
            route('admin.documents.index'), route('files.index'), route('notifications.index'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_student_pages_render(): void
    {
        foreach (['s1@capstone.test', 's6@capstone.test', 's9@capstone.test', 's10@capstone.test', 's14@capstone.test'] as $email) {
            $student = User::query()->where('email', $email)->first();
            foreach ([
                'mahasiswa.dashboard', 'mahasiswa.team.show', 'mahasiswa.topic.index', 'mahasiswa.bank.index', 'mahasiswa.documents.index',
                'mahasiswa.logbooks.index', 'mahasiswa.peer.index', 'mahasiswa.results.index', 'mahasiswa.qr.show', 'files.index',
            ] as $route) {
                $this->actingAs($student)->get(route($route))->assertOk();
            }
        }
    }

    public function test_lecturer_pages_render(): void
    {
        $team = Team::query()->where('code', 'KP-SI-01')->first();
        $lecturer = User::query()->where('email', 'dsn1@capstone.test')->first();

        foreach ([
            route('dosen.dashboard'), route('dosen.topics.index'), route('dosen.topics.create'), route('dosen.applications.index'),
            route('dosen.teams.show', $team), route('dosen.logbooks.index'), route('dosen.assessments.index'),
            route('dosen.assessments.edit', [$team, 'proposal']), route('dosen.recap.index'),
        ] as $url) {
            $this->actingAs($lecturer)->get($url)->assertOk();
        }
    }

    public function test_roles_cannot_cross_access(): void
    {
        $student = User::query()->where('email', 's1@capstone.test')->first();
        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($student)->get(route('dosen.dashboard'))->assertForbidden();
    }
}
