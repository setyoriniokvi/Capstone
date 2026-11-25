<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dropdown "Urutkan" di setiap tabel / daftar data: data tetap bawaan kolom paling kiri naik (A–Z / 0–9), data menunggu
 * bawaan terbaru turun. Daftar berhalaman di server memakai ?urut=&arah=.
 */
class SortingTest extends TestCase
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

    public function test_server_sorted_lists_default_to_leftmost_column_and_can_be_changed(): void
    {
        // Mahasiswa: bawaan NIM naik
        $nims = $this->asAdmin()->get(route('admin.students.index'))->assertOk()
            ->assertSee('id="urut-mahasiswa"', false)->assertSee('Kategori (Peminatan)')
            ->viewData('students')->pluck('nim')->all();
        $sorted = $nims;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $nims);

        // Nama turun
        $names = $this->asAdmin()->get(route('admin.students.index', ['urut' => 'nama', 'arah' => 'desc']))->assertOk()
            ->viewData('students')->pluck('name')->all();
        $expected = $names;
        usort($expected, fn ($a, $b) => strcmp($b, $a));
        $this->assertSame($expected, $names);

        // Kunci tidak dikenal kembali ke bawaan; filter tetap membawa urutan yang dipilih
        $this->asAdmin()->get(route('admin.students.index', ['urut' => 'password']))->assertOk();
        $this->asAdmin()->get(route('admin.students.index', ['urut' => 'nama', 'arah' => 'desc']))
            ->assertSee('name="urut" value="nama"', false);

        // Dosen, Master Data, Sinkronisasi Dokumen, Tim Mahasiswa, Reminder
        $this->asAdmin()->get(route('admin.lecturers.index', ['urut' => 'kategori']))->assertOk()->assertSee('id="urut-dosen"', false);
        $this->asAdmin()->get(route('admin.master-data.index', ['urut' => 'nama']))->assertOk()->assertDontSee('id="urut-log"', false);
        $this->asAdmin()->get(route('admin.documents.index', ['urut' => 'tim']))->assertOk()->assertSee('id="urut-dokumen"', false);
        $this->asAdmin()->get(route('admin.monitoring.progress', ['urut' => 'kategori', 'arah' => 'desc']))->assertOk()->assertSee('id="urut-progres"', false);
        $this->asAdmin()->get(route('admin.reminders.index', ['urut' => 'nama']))->assertOk()->assertSee('id="urut-pengiriman"', false);
        $this->asAdmin()->get(route('admin.teams.index', ['urut' => 'kategori']))->assertOk();
    }

    public function test_client_sorted_lists_carry_sort_keys(): void
    {
        // Data menunggu: bawaan terbaru turun
        $this->asAdmin()->get(route('admin.teams.index'))->assertOk()
            ->assertSee('data-sort-scope', false)->assertSee('data-default="terbaru" data-dir="desc"', false)
            ->assertDontSee('id="urut-riwayat-tim"', false);
        // Riwayat registrasi tim pindah ke Master Data Mahasiswa; Sort-nya berparameter sendiri (tidak bentrok dengan Sort log)
        $this->asAdmin()->get(route('admin.master-data.index', ['urut_riwayat' => 'kategori', 'arah_riwayat' => 'desc']))->assertOk()
            ->assertSee('id="urut-riwayat-tim"', false)->assertSee('Log Registrasi Tim')->assertSee('urut_riwayat=', false);
        $this->asAdmin()->get(route('admin.master-data.index', ['tab' => 'dosen']))->assertOk()->assertDontSee('Log Registrasi Tim');
        // Data tetap: bawaan kolom paling kiri naik
        $this->asAdmin()->get(route('admin.lecturer-topics.index'))->assertOk()
            ->assertSee('data-default="topik" data-dir="asc"', false)->assertSee('data-sort-topik=', false)->assertSee('data-sort-no', false);
        foreach (['admin.quotas.index', 'admin.plotting.index', 'admin.clos.index', 'admin.indicators.index', 'admin.rubrics.index',
            'admin.oral.index', 'admin.peer-rubric.index', 'admin.files.index', 'admin.recap.index', 'admin.monitoring.assessments'] as $route) {
            $this->asAdmin()->get(route($route))->assertOk()->assertSee('x-data="sortBy"', false);
        }

        $lecturer = User::query()->where('role', 'dosen')->whereHas('lecturerAssignments')->first()
            ?? User::query()->where('role', 'dosen')->first();
        foreach (['dosen.topics.index', 'dosen.applications.index'] as $route) {
            $this->actingAs($lecturer)->get(route($route))->assertOk()->assertSee('x-data="sortBy"', false);
        }
    }
}
