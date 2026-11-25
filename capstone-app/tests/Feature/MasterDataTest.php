<?php

namespace Tests\Feature;

use App\Models\LecturerProfile;
use App\Models\MasterDataLog;
use App\Models\Period;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Master Data: log data mahasiswa / dosen (sebelum & sesudah) dengan tab dan jumlah data per halaman. */
class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
    }

    private function asAdmin(): static
    {
        return $this->actingAs(User::query()->where('role', 'admin')->first())->withSession([Period::WORKSPACE_KEY => Period::active()->id]);
    }

    public function test_admin_email_fix_is_logged_with_before_and_after(): void
    {
        $student = User::query()->students()->whereNotNull('nim')->first();
        $old = $student->email;
        $this->asAdmin()->put(route('admin.students.update', $student), [
            'nim' => $student->nim, 'name' => $student->name, 'email' => 'email.benar@student.test', 'phone' => '081234567890',
            'sks_passed' => 120, 'origin_region' => 'Kota Bandung',
        ])->assertSessionHasNoErrors();

        $log = MasterDataLog::query()->where('audience', 'mahasiswa')->where('event', 'updated')->where('subject_user_id', $student->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('Akun Mahasiswa', $log->entity);
        $email = collect($log->changes)->firstWhere('field', 'email');
        $this->assertSame([$old, 'email.benar@student.test'], [$email['before'], $email['after']]);
        $this->assertStringContainsString('(admin)', $log->actor_label);

        // Log tetap tercatat, tetapi kartu Log Perubahan Data tidak ditampilkan di Master Data
        $this->asAdmin()->get(route('admin.master-data.index'))->assertOk()
            ->assertSee('Master Data Mahasiswa')->assertSee('Master Data Dosen')
            ->assertDontSee('Log Perubahan Data')->assertDontSee('id="per-halaman-log"', false)->assertSeeInOrder(['Rubrik Peer Review', 'Master Data']);
    }

    public function test_lecturer_tab_and_page_size(): void
    {
        $profile = LecturerProfile::query()->first();
        $this->asAdmin();
        $profile->update(['nip' => '199901012020']);

        $this->asAdmin()->get(route('admin.master-data.index', ['tab' => 'dosen']))->assertOk()
            ->assertSee('Log Status Dosen')->assertDontSee('Log Perubahan Data Dosen');
        $this->assertTrue(MasterDataLog::query()->where('audience', 'dosen')->where('changes', 'like', '%199901012020%')->exists());
        $this->assertGreaterThan(10, MasterDataLog::query()->where('audience', 'mahasiswa')->count()); // data seeder ikut tercatat
    }

    public function test_existing_data_is_backfilled_once(): void
    {
        MasterDataLog::query()->delete(); // seperti database lama sebelum fitur log dipasang
        $student = User::query()->students()->first();

        $added = (new \App\Services\MasterData\MasterDataLogger)->backfill();
        $this->assertGreaterThan(0, $added);
        $log = MasterDataLog::query()->where('entity_type', $student->getMorphClass())->where('entity_id', $student->id)->sole();
        $this->assertSame(['created', 'Sistem (data awal)', 'Akun Mahasiswa'], [$log->event, $log->actor_label, $log->entity]);
        $this->assertSame($student->created_at->toDateTimeString(), $log->created_at->toDateTimeString());
        $this->assertSame($student->email, collect($log->changes)->firstWhere('field', 'email')['after']);

        $this->assertSame(0, (new \App\Services\MasterData\MasterDataLogger)->backfill()); // tidak dobel
    }
}
