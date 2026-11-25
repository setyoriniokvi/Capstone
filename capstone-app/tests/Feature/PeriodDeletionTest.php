<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Hapus periode dari Kelola Periode: periode kosong cukup dikonfirmasi; periode yang sudah punya tim harus dinonaktifkan
 * dulu dan admin mengetik nama periode. Seluruh isi periode ikut terhapus (cascade), begitu pula berkas lokalnya.
 */
class PeriodDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::query()->where('role', 'admin')->firstOrFail();
    }

    public function test_kelola_periode_shows_delete_button_for_every_period(): void
    {
        $period = Period::active();
        $html = $this->actingAs($this->admin())->get(route('admin.periods.index'))->assertOk()->getContent();

        $this->assertSame(Period::count(), substr_count($html, 'Hapus Periode'));
        // Periode aktif yang punya tim: tombol tampil tetapi nonaktif, dialog meminta nama periode
        $this->assertStringContainsString('data-confirm-type="'.e($period->name).'"', $html);
        $this->assertStringContainsString('Nonaktifkan periode ini terlebih dahulu sebelum menghapusnya', $html);
    }

    public function test_empty_period_is_deleted_with_plain_confirmation(): void
    {
        $period = Period::create(['name' => 'Periode Kosong', 'academic_year' => '2030/2031', 'semester' => 'ganjil',
            'starts_on' => '2030-09-01', 'ends_on' => '2031-01-31', 'is_active' => false]);

        $this->actingAs($this->admin())->delete(route('admin.periods.destroy', $period))->assertRedirect(route('admin.periods.index'));
        $this->assertModelMissing($period);
    }

    public function test_period_with_teams_must_be_inactive_and_name_typed(): void
    {
        Storage::fake('local');
        $period = Period::active();
        $team = Team::query()->where('period_id', $period->id)->firstOrFail();
        $member = $team->members()->firstOrFail();
        Storage::disk('local')->put('registrations/khs-test.pdf', 'x');
        $member->update(['khs_path' => 'registrations/khs-test.pdf']);

        // Masih aktif → ditolak
        $this->actingAs($this->admin())->from(route('admin.periods.index'))
            ->delete(route('admin.periods.destroy', $period), ['confirm_name' => $period->name])
            ->assertSessionHasErrors('period');
        $this->assertModelExists($period);

        // Nonaktif, nama salah → ditolak
        $period->update(['is_active' => false]);
        $this->actingAs($this->admin())->from(route('admin.periods.index'))
            ->delete(route('admin.periods.destroy', $period), ['confirm_name' => 'salah'])
            ->assertSessionHasErrors('period');
        $this->assertModelExists($period);

        // Nonaktif, nama benar → periode, tim, dan berkasnya terhapus
        $this->actingAs($this->admin())->delete(route('admin.periods.destroy', $period), ['confirm_name' => $period->name])
            ->assertRedirect(route('admin.periods.index'))->assertSessionHasNoErrors();
        $this->assertModelMissing($period);
        $this->assertModelMissing($team);
        $this->assertSame(0, Team::query()->where('period_id', $period->id)->count());
        Storage::disk('local')->assertMissing('registrations/khs-test.pdf');
    }
}
