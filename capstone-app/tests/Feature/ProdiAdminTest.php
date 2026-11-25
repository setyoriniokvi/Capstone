<?php

namespace Tests\Feature;

use App\Models\Clo;
use App\Models\Indicator;
use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Admin fakultas (CRUD periode) & admin prodi (satu akun per periode, dapat dipakai beberapa periode); data master per periode. */
class ProdiAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
    }

    private function faculty(): User
    {
        return User::query()->where('role', 'admin')->first();
    }

    /** Buat periode lewat formulir admin fakultas (Kelola Periode). */
    private function createPeriod(string $name, array $extra = []): Period
    {
        $this->actingAs($this->faculty())->post(route('admin.periods.store'), [
            'name' => $name, 'academic_year' => '2027/2028', 'semester' => 'genap', 'prodi_name' => 'S1 Teknik Industri',
            'starts_on' => '2027-02-01', 'ends_on' => '2027-06-30', 'min_approved_logbooks' => 4,
            'rule' => ['min_sks' => 100, 'min_members' => 2, 'max_members' => 4],
        ] + $extra)->assertSessionHasNoErrors();

        return Period::query()->where('name', $name)->firstOrFail();
    }

    private function identity(Period $period, array $extra = []): array
    {
        return ['name' => $period->name, 'academic_year' => $period->academic_year, 'semester' => $period->semester,
            'prodi_name' => $period->prodi_name, 'starts_on' => $period->starts_on->toDateString(), 'ends_on' => $period->ends_on->toDateString(),
            'min_approved_logbooks' => 4, 'rule' => ['min_sks' => 100, 'min_members' => 2, 'max_members' => 4]] + $extra;
    }

    public function test_prodi_admin_logs_in_straight_to_own_period_dashboard(): void
    {
        $demo = Period::active();
        $this->assertSame('adminprodi@gmail.com', $demo->admin->email);

        $this->post(route('login.attempt'), ['email' => 'adminprodi@gmail.com', 'password' => config('capstone.demo_password')])
            ->assertRedirect(route('admin.dashboard'))->assertSessionHas(Period::WORKSPACE_KEY, $demo->id);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Approval Registrasi Tim')
            ->assertDontSee('Keluar dari Dashboard Periode ini');

        // Pengaturan & CRUD periode hanya untuk admin fakultas
        $this->get(route('admin.periods.index'))->assertForbidden();
        $this->get(route('admin.periods.edit', $demo))->assertForbidden();
        $this->post(route('admin.workspace.leave'))->assertForbidden();
        $this->get('/')->assertRedirect(route('admin.dashboard'));
    }

    public function test_prodi_admin_without_period_cannot_log_in(): void
    {
        User::create(['name' => 'Admin Lepas', 'email' => 'lepas@gmail.com', 'role' => 'admin_prodi', 'password' => 'rahasia123']);

        $this->post(route('login.attempt'), ['email' => 'lepas@gmail.com', 'password' => 'rahasia123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_faculty_admin_sets_prodi_admin_account_and_shared_email_switches_periods(): void
    {
        $demo = Period::active();
        $other = $this->createPeriod('Capstone TI Genap', ['admin_email' => 'admin.ti@gmail.com', 'admin_password' => 'passwordti1']);
        $ti = User::query()->where('email', 'admin.ti@gmail.com')->firstOrFail();
        $this->assertSame('admin_prodi', $ti->role);
        $this->assertSame($ti->id, $other->admin_user_id);

        // Email milik akun peran lain ditolak; akun baru wajib password
        $this->actingAs($this->faculty())->put(route('admin.periods.update', $other), $this->identity($other, ['admin_email' => 'dosen@gmail.com']))
            ->assertSessionHasErrors('admin_email');
        $this->actingAs($this->faculty())->put(route('admin.periods.update', $other), $this->identity($other, ['admin_email' => 'baru@gmail.com']))
            ->assertSessionHasErrors('admin_password');

        // Periode ketiga memakai email yang sama → satu akun, dapat berganti di antara keduanya
        $third = $this->createPeriod('Capstone TI Ganjil', ['admin_email' => 'ADMIN.TI@gmail.com']);
        $this->assertSame($ti->id, $third->admin_user_id);
        $this->assertSame(1, User::query()->where('email', 'admin.ti@gmail.com')->count());
        $this->actingAs($this->faculty())->get(route('admin.periods.edit', $third))->assertOk()
            ->assertSee('Akun ini juga dipakai 1 periode lain')->assertSee('Capstone TI Genap');

        $this->flushSession();
        $this->post('/logout');
        $this->post(route('login.attempt'), ['email' => 'admin.ti@gmail.com', 'password' => 'passwordti1'])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Capstone TI Genap')->assertSee('Capstone TI Ganjil')->assertDontSee($demo->name);
        $this->post(route('admin.workspace.enter', $third))->assertRedirect(route('admin.dashboard'));
        $this->assertSame($third->id, session(Period::WORKSPACE_KEY));
        // Periode yang tidak memakai akun ini tidak dapat dibuka
        $this->post(route('admin.workspace.enter', $demo))->assertForbidden();
        $team = Team::query()->where('period_id', $demo->id)->firstOrFail();
        $this->get(route('admin.teams.show', $team))->assertNotFound();
    }

    public function test_one_account_works_on_several_devices_and_password_change_signs_them_out(): void
    {
        $period = $this->createPeriod('Capstone TI Genap', ['admin_email' => 'admin.ti@gmail.com', 'admin_password' => 'passwordti1']);
        $user = User::query()->where('email', 'admin.ti@gmail.com')->firstOrFail();
        $user->forceFill(['remember_token' => 'token-perangkat-lain'])->save();

        // Keluar di satu perangkat tidak mengganti token "ingat saya" perangkat lain
        $this->actingAs($user)->withSession([Period::WORKSPACE_KEY => $period->id])->post('/logout')->assertRedirect(route('login'));
        $this->assertSame('token-perangkat-lain', $user->fresh()->remember_token);

        // Sesi perangkat lain dikeluarkan saat admin fakultas mengganti password
        DB::table('sessions')->insert(['id' => 'perangkat-2', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($this->faculty())->put(route('admin.periods.update', $period), $this->identity($period, ['admin_email' => 'admin.ti@gmail.com', 'admin_password' => 'passwordbaru2']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'perangkat-2']);
        $this->assertNotSame('token-perangkat-lain', $user->fresh()->remember_token);
    }

    public function test_master_data_belongs_to_each_period(): void
    {
        $demo = Period::active();
        $copy = $this->createPeriod('Salinan Demo', ['master_source' => $demo->id]);
        $blank = $this->createPeriod('Dari Bawaan');

        // Periode baru menyalin data master (CLO, indikator, dosen) dari sumbernya
        $this->assertSame(Clo::inPeriod($demo)->count(), Clo::inPeriod($copy)->count());
        $this->assertSame(Indicator::inPeriod($demo)->count(), Indicator::inPeriod($copy)->count());
        $this->assertSame(Clo::inPeriod(null)->count(), Clo::inPeriod($blank)->count());
        $this->assertSame($demo->members()->where('role', 'dosen')->count(), $copy->members()->where('role', 'dosen')->count());
        $this->assertSame(0, $blank->members()->count());

        // Perubahan di satu periode tidak memengaruhi periode lain
        $admin = $this->faculty();
        $this->actingAs($admin)->withSession([Period::WORKSPACE_KEY => $copy->id])
            ->post(route('admin.clos.store'), ['code' => 'CLO9', 'description' => 'Khusus salinan', 'weight' => 0])->assertSessionHasNoErrors();
        $this->assertTrue(Clo::inPeriod($copy)->where('code', 'CLO9')->exists());
        $this->assertFalse(Clo::inPeriod($demo)->where('code', 'CLO9')->exists());
        $this->actingAs($admin)->withSession([Period::WORKSPACE_KEY => $demo->id])->get(route('admin.clos.index'))->assertOk()->assertDontSee('Khusus salinan');
        $this->actingAs($admin)->withSession([Period::WORKSPACE_KEY => $copy->id])->get(route('admin.clos.index'))->assertOk()->assertSee('Khusus salinan');

        // Daftar dosen & status aktif per periode
        $lecturer = User::query()->where('email', 'dosen@gmail.com')->first();
        $this->actingAs($admin)->withSession([Period::WORKSPACE_KEY => $copy->id])->post(route('admin.lecturers.toggle', $lecturer))->assertSessionHasNoErrors();
        $this->assertFalse($lecturer->activeInPeriod($copy));
        $this->assertTrue($lecturer->activeInPeriod($demo));
        $this->assertTrue($lecturer->fresh()->is_active);
        $this->actingAs($admin)->withSession([Period::WORKSPACE_KEY => $blank->id])->get(route('admin.lecturers.index'))->assertOk()->assertDontSee('dosen@gmail.com');
        $this->actingAs($admin)->withSession([Period::WORKSPACE_KEY => $blank->id])
            ->post(route('admin.lecturers.store'), ['email' => 'dosen@gmail.com'])->assertSessionHasNoErrors();
        $this->assertTrue($lecturer->periods()->whereKey($blank->id)->exists());
    }
}
