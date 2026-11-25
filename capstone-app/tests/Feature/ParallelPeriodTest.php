<?php

namespace Tests\Feature;

use App\Models\LecturerProfile;
use App\Models\Period;
use App\Models\ReminderDispatch;
use App\Models\User;
use App\Services\ReminderService;
use App\Support\PeriodContext;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Beberapa periode aktif bersamaan (prodi paralel), menu ganti periode dosen/mahasiswa, dan profil dosen per periode. */
class ParallelPeriodTest extends TestCase
{
    use RefreshDatabase;

    private Period $si;

    private Period $ti;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();

        $this->si = Period::active();
        $admin = User::query()->where('role', 'admin')->first();
        $this->actingAs($admin)->post(route('admin.periods.store'), [
            'name' => 'Capstone TI', 'academic_year' => $this->si->academic_year, 'semester' => $this->si->semester, 'prodi_name' => 'S1 Teknik Industri',
            'starts_on' => $this->si->starts_on->toDateString(), 'ends_on' => $this->si->ends_on->toDateString(), 'min_approved_logbooks' => 4,
            'rule' => ['min_sks' => 100, 'min_members' => 2, 'max_members' => 4], 'master_source' => $this->si->id,
        ])->assertSessionHasNoErrors();
        $this->ti = Period::query()->where('name', 'Capstone TI')->firstOrFail();
        $this->actingAs($admin)->post(route('admin.periods.activate', $this->ti))->assertSessionHasNoErrors();
        $this->flushSession();
        $this->post('/logout');
    }

    public function test_several_periods_stay_active_together(): void
    {
        $this->assertTrue($this->si->fresh()->is_active);
        $this->assertTrue($this->ti->fresh()->is_active);
    }

    public function test_lecturer_in_two_periods_switches_per_session_and_has_profile_per_period(): void
    {
        $lecturer = User::query()->where('email', 'dosen@gmail.com')->first();
        // Profil disalin dari periode sumber, lalu diubah khusus periode TI
        $tiProfile = LecturerProfile::inPeriod($this->ti)->where('user_id', $lecturer->id)->firstOrFail();
        $tiProfile->update(['lecturer_code' => 'TIX', 'phone' => '089900001111']);
        $this->assertSame('RWL', LecturerProfile::inPeriod($this->si)->where('user_id', $lecturer->id)->value('lecturer_code'));

        $this->post(route('login.attempt'), ['email' => 'dosen@gmail.com', 'password' => config('capstone.demo_password')])->assertRedirect(route('dosen.dashboard'));
        $page = $this->get(route('dosen.dashboard'))->assertOk()->assertSee('Ganti periode')->assertSee('S1 Teknik Industri');
        $current = Period::active();
        $other = $current->id === $this->si->id ? $this->ti : $this->si;

        $this->post(route('period.switch', $other))->assertRedirect(route('dosen.dashboard'));
        $this->assertSame($other->id, session(PeriodContext::USER_KEY));
        $this->get(route('dosen.dashboard'))->assertOk()->assertSee($other->id === $this->ti->id ? 'TIX' : 'RWL');
        $this->assertSame($other->id === $this->ti->id ? '089900001111' : '081200000001', $lecturer->fresh()->waNumber());

        // Periode yang tidak diikuti dosen tidak dapat dipilih
        $foreign = Period::create(['name' => 'Lain', 'academic_year' => '2030/2031', 'semester' => 'ganjil', 'prodi_name' => 'S1 X',
            'starts_on' => '2030-08-01', 'ends_on' => '2030-12-31', 'is_active' => true]);
        $this->post(route('period.switch', $foreign))->assertForbidden();
    }

    public function test_admin_edits_lecturer_profile_only_for_open_period(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $lecturer = User::query()->where('email', 'dosen@gmail.com')->first();

        $this->actingAs($admin)->withSession([Period::WORKSPACE_KEY => $this->ti->id])->put(route('admin.lecturers.update', $lecturer), [
            'name' => $lecturer->name, 'email' => $lecturer->email, 'phone' => '081277778888',
            'lecturer_code' => 'RTI', 'nip' => '99887766', 'specialization' => 'Teknik Industri',
        ])->assertSessionHasNoErrors();

        $this->assertSame('RTI', LecturerProfile::inPeriod($this->ti)->where('user_id', $lecturer->id)->value('lecturer_code'));
        $this->assertSame('081277778888', LecturerProfile::inPeriod($this->ti)->where('user_id', $lecturer->id)->value('phone'));
        $this->assertSame('RWL', LecturerProfile::inPeriod($this->si)->where('user_id', $lecturer->id)->value('lecturer_code'));
        $this->actingAs($admin)->withSession([Period::WORKSPACE_KEY => $this->si->id])->get(route('admin.lecturers.index'))->assertOk()
            ->assertSee('RWL')->assertDontSee('RTI');
    }

    public function test_registration_chooses_among_active_periods(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('Daftar ke prodi / periode')
            ->assertSee('S1 Teknik Industri')->assertSee('S1 Sistem Informasi');
        $this->get(route('register', ['periode' => $this->ti->id]))->assertOk()
            ->assertSee('name="period_id" value="'.$this->ti->id.'"', false);
    }

    public function test_reminders_are_queued_per_active_period(): void
    {
        app(ReminderService::class)->queueDue(now()->addWeeks(16));
        $this->assertSame(0, ReminderDispatch::query()->whereNull('period_id')->count());
    }
}
