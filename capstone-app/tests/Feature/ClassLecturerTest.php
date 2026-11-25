<?php

namespace Tests\Feature;

use App\Models\LecturerProfile;
use App\Models\Period;
use App\Models\User;
use App\Services\LoadBalanceService;
use App\Support\PeriodContext;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Hak akses Dosen Kelas: centang di data dosen, impor menurut kode dosen, dan cakupan kuota dosen kelas. */
class ClassLecturerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Period $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
        $this->admin = User::query()->where('role', 'admin')->first();
        $this->period = Period::active();
        $this->withSession([Period::WORKSPACE_KEY => $this->period->id]);
    }

    private function profile(string $code): LecturerProfile
    {
        return LecturerProfile::inPeriod($this->period)->where('lecturer_code', $code)->firstOrFail();
    }

    public function test_admin_ticks_class_lecturer_access_and_filters_by_it(): void
    {
        $lecturer = $this->profile('HSP')->user;
        $profile = $this->profile('HSP');
        $this->actingAs($this->admin)->put(route('admin.lecturers.update', $lecturer), [
            'name' => $lecturer->name, 'email' => $lecturer->email, 'phone' => '081200000002',
            'lecturer_code' => 'HSP', 'nip' => $profile->nip ?: '1234567', 'specialization' => $profile->specialization,
            'is_class_lecturer' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Dosen', 'Dosen Kelas'], $this->profile('HSP')->accessLabels());
        $this->actingAs($this->admin)->get(route('admin.lecturers.index', ['akses' => 'is_class_lecturer']))->assertOk()
            ->assertSee($lecturer->email)->assertDontSee('dosen@gmail.com');
    }

    public function test_import_by_lecturer_code_adds_access(): void
    {
        $csv = "kode_dosen,hak_akses\n"
            ."RWL,dosen_kelas\n"
            ."hsp,Dosen Kelas; penguji\n"
            ."XXX,dosen_kelas\n"
            ."MKT,asisten\n";
        $this->actingAs($this->admin)->post(route('admin.imports.preview', 'dosen'), ['file' => UploadedFile::fake()->createWithContent('kelas.csv', $csv)])
            ->assertRedirect(route('admin.imports.show', 'dosen'));
        $this->actingAs($this->admin)->get(route('admin.imports.show', 'dosen'))->assertOk()
            ->assertSee('Kode dosen tidak ditemukan di periode ini.')->assertSee('Hak akses tidak dikenal: asisten.');

        $token = Cache::get("import:{$this->admin->id}:dosen")['token'];
        $this->actingAs($this->admin)->post(route('admin.imports.commit', 'dosen'), ['token' => $token])->assertSessionHasNoErrors();

        $this->assertTrue($this->profile('RWL')->is_class_lecturer);
        $this->assertTrue($this->profile('HSP')->is_class_lecturer);
        $this->assertTrue($this->profile('HSP')->can_examine);
        $this->assertFalse($this->profile('MKT')->is_class_lecturer);
        // Hak akses ditambahkan, data lain tidak tertimpa
        $this->assertSame('Enterprise Intelligence System Development', $this->profile('RWL')->specialization);
    }

    public function test_class_lecturer_quota_sits_between_global_and_specific(): void
    {
        $values = ['supervise_min' => 0, 'examine_min' => 0];
        $this->actingAs($this->admin)->post(route('admin.quotas.store'), ['scope' => 'global', 'supervise_max' => 5, 'examine_max' => 5] + $values)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.quotas.store'), ['scope' => 'dosen_kelas', 'supervise_max' => 3, 'examine_max' => 2] + $values)->assertSessionHasNoErrors();
        $this->profile('RWL')->update(['is_class_lecturer' => true]);
        $this->profile('HSP')->update(['is_class_lecturer' => true]);
        $hsp = $this->profile('HSP')->user;
        $this->actingAs($this->admin)->post(route('admin.quotas.store'), ['scope' => 'dosen', 'lecturer_ids' => [$hsp->id], 'supervise_max' => 1, 'examine_max' => 1] + $values)->assertSessionHasNoErrors();

        PeriodContext::using($this->period, function () use ($hsp) {
            $balance = new LoadBalanceService;
            $quota = fn (string $code) => $balance->quotaFor($this->profile($code)->user()->first(), $this->period);
            $this->assertSame(['max' => 3, 'source' => 'dosen_kelas'], ['max' => $quota('RWL')['pembimbing']['max'], 'source' => $quota('RWL')['source']]);
            $this->assertSame(5, $quota('MKT')['pembimbing']['max']);
            $this->assertSame('global', $quota('MKT')['source']);
            $this->assertSame(1, $quota('HSP')['pembimbing']['max']);
        });

        $this->actingAs($this->admin)->get(route('admin.quotas.index'))->assertOk()->assertSee('Semua dosen kelas')->assertSee('Dosen Kelas');
    }
}
