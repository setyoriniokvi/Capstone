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
        // Admin bekerja di dalam Dashboard Periode (periode aktif demo)
        $this->withSession([\App\Models\Period::WORKSPACE_KEY => \App\Models\Period::active()->id]);
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
            route('admin.lecturers.index'), route('admin.lecturers.create'),
            route('admin.lecturers.edit', User::query()->where('role', 'dosen')->first()),
            route('admin.students.index'), route('admin.students.edit', User::query()->where('role', 'mahasiswa')->first()),
            route('admin.teams.index'), route('admin.teams.show', $team), route('admin.topics.index'), route('admin.topics.index', ['sumber' => 'dosen', 'status' => 'semua']),
            route('admin.topics.recap'), route('admin.quotas.index'), route('admin.plotting.index'),
            route('admin.monitoring.progress'), route('admin.monitoring.assessments'),
            route('admin.clos.index'), route('admin.indicators.index'), route('admin.indicators.create'),
            route('admin.indicators.edit', Indicator::first()), route('admin.rubrics.index'), route('admin.rubrics.edit', Indicator::first()),
            route('admin.oral.index'), route('admin.files.index'), route('admin.recap.index'), route('admin.reminders.index'),
            route('admin.documents.index'), route('files.index'),
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_student_pages_render(): void
    {
        foreach (['mahasiswa@gmail.com', 'mahasiswa6@gmail.com', 'mahasiswa9@gmail.com', 'mahasiswa10@gmail.com', 'mahasiswa14@gmail.com'] as $email) {
            $student = User::query()->where('email', $email)->first();
            foreach ([
                'mahasiswa.dashboard', 'mahasiswa.team.show', 'mahasiswa.topic.index', 'mahasiswa.documents.index',
                'mahasiswa.logbooks.index', 'mahasiswa.peer.index', 'mahasiswa.results.index', 'mahasiswa.qr.show', 'files.index',
            ] as $route) {
                $this->actingAs($student)->get(route($route))->assertOk();
            }
            $this->actingAs($student)->get(route('mahasiswa.topic.index', ['tab' => 'dosen', 'q' => 'data']))->assertOk();
            $this->actingAs($student)->get(route('mahasiswa.bank.index'))->assertRedirect(route('mahasiswa.topic.index', ['tab' => 'dosen']));
            if ($email === 'mahasiswa@gmail.com') {
                $this->actingAs($student)->get(route('mahasiswa.qr.download'))->assertOk()->assertHeader('Content-Type', 'image/png');
                // QR baru menggantikan yang lama (token lama dicabut)
                $old = $student->teamFor(\App\Models\Period::active())->activeQrToken()->token;
                $this->actingAs($student)->post(route('mahasiswa.qr.regenerate'))->assertRedirect(route('mahasiswa.qr.show'));
                $this->assertNotSame($old, $student->teamFor(\App\Models\Period::active())->activeQrToken()->token);
                $this->get(route('qr.resolve', $old))->assertStatus(410);
            }
        }
    }

    public function test_lecturer_pages_render(): void
    {
        $team = Team::query()->where('code', 'KP-SI-01')->first();
        $lecturer = User::query()->where('email', 'dosen@gmail.com')->first();

        foreach ([
            route('dosen.dashboard'), route('dosen.topics.index'), route('dosen.topics.index', ['tambah' => 1]), route('dosen.applications.index'),
            route('dosen.teams.index'), route('dosen.teams.show', $team), route('dosen.logbooks.index'), route('dosen.assessments.index'),
            route('dosen.assessments.edit', [$team, 'proposal']), route('dosen.recap.index'),
        ] as $url) {
            $this->actingAs($lecturer)->get($url)->assertOk();
        }
    }

    public function test_admin_works_inside_a_chosen_period_workspace(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $period = \App\Models\Period::active();
        $this->flushSession();

        // Tanpa periode terbuka → dashboard awal Kelola Periode
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.periods.index'));
        $this->actingAs($admin)->get(route('admin.periods.index'))->assertOk()->assertSee('Kelola Periode')->assertSee('Mahasiswa terdaftar')->assertDontSee('Keluar dari Dashboard Periode ini')->assertDontSee('id="sidebar"', false);

        // Aturan registrasi (satu per periode) ikut tersimpan bersama periode
        $this->actingAs($admin)->get(route('admin.periods.edit', $period))->assertOk()->assertSee('Aturan registrasi tim');

        // Identitas periode wajib nama prodi; milestone berjenis Dokumen / Lainnya
        $identity = ['name' => $period->name, 'academic_year' => $period->academic_year, 'semester' => $period->semester,
            'starts_on' => $period->starts_on->toDateString(), 'ends_on' => $period->ends_on->toDateString(),
            'rule' => ['min_sks' => 100, 'min_members' => 2, 'max_members' => 3, 'require_distinct_region' => '1', 'require_distinct_specialization' => '0',
                'courses' => "KP - Kerja Praktek
MC1 - Micro-course Data", 'specializations' => "Enterprise Systems
Logistics"]];
        $this->actingAs($admin)->put(route('admin.periods.update', $period), $identity)->assertSessionHasErrors('prodi_name');
        $this->actingAs($admin)->put(route('admin.periods.update', $period), $identity + ['prodi_name' => 'S1 Teknik Industri'])->assertSessionHasNoErrors();
        $this->assertSame('S1 Teknik Industri', $period->fresh()->prodi_name);
        $rule = $period->fresh()->registrationRule;
        $this->assertSame(1, $period->registrationRules()->count());
        $this->assertSame(100, $rule->min_sks);
        $this->assertFalse($rule->require_distinct_specialization);
        $this->assertSame(['KP', 'MC1'], $rule->courses()->orderBy('code')->pluck('code')->all());
        $this->assertSame(['Enterprise Systems', 'Logistics'], $rule->specializations()->pluck('name')->all());
        $times = ['starts_at' => now()->toDateTimeString(), 'due_at' => now()->addWeek()->toDateTimeString()];
        $this->actingAs($admin)->post(route('admin.milestones.store', $period), ['category' => 'lainnya', 'kind' => 'registrasi_tim', 'name' => 'Registrasi gelombang 2'] + $times)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.milestones.store', $period), ['category' => 'dokumen', 'document_type' => 'poster', 'allowed_types' => ['pdf'], 'name' => 'Poster tambahan'] + $times)->assertSessionHasNoErrors();
        $this->assertSame('registrasi_tim', $period->milestones()->where('name', 'Registrasi gelombang 2')->value('kind'));
        $this->assertSame('dokumen', $period->milestones()->where('name', 'Poster tambahan')->value('kind'));

        // Buka dashboard periode → menu lengkap + dropdown ganti periode
        $this->actingAs($admin)->post(route('admin.workspace.enter', $period))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Keluar dari Dashboard Periode ini')->assertDontSee('Periode &amp; Timeline', false);

        $this->actingAs($admin)->post(route('admin.workspace.leave'))->assertRedirect(route('admin.periods.index'));
        $this->actingAs($admin)->get(route('admin.teams.index'))->assertRedirect(route('admin.periods.index'));

        // Nonaktifkan lalu aktifkan kembali periode
        $this->actingAs($admin)->post(route('admin.periods.deactivate', $period))->assertSessionHasNoErrors();
        $this->assertFalse($period->fresh()->is_active);
        $this->actingAs($admin)->post(route('admin.periods.activate', $period));
        $this->assertTrue($period->fresh()->is_active);
    }

    public function test_roles_cannot_cross_access(): void
    {
        $student = User::query()->where('email', 'mahasiswa@gmail.com')->first();
        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($student)->get(route('dosen.dashboard'))->assertForbidden();
    }
}
