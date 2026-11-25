<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Tabel Monitoring penilaian, Sinkronisasi Dokumen, Rekap Nilai, dan urutan menu Pemantauan admin. */
class MonitoringTablesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
        $this->admin = User::query()->where('role', 'admin')->first();
        $this->withSession([Period::WORKSPACE_KEY => Period::active()->id]);
    }

    public function test_monitoring_assessments_table_and_controls(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.monitoring.assessments'))->assertOk()
            ->assertSeeInOrder(['<th>Dosen</th>', '<th>Tim Uji</th>', '<th>Tim Bimbing</th>', '<th>Komponen Penilaian</th>', '<th>Reminder terakhir</th>'], false)
            ->assertDontSee('<th>Status</th>', false)
            ->assertSee('Tim uji / bimbing')->assertSee('data-filter-own', false)->assertSee('<option value="pembimbing">Tim Bimbing</option>', false)
            ->assertSeeInOrder(['id="cari-dosen"', 'id="urut-monitoring-nilai"', 'id="peran"', 'id="status"'], false)
            ->assertSee('data-page-size="10"', false)->assertSee('<option value="0">Semua</option>', false)
            ->assertSee('tim)')->assertDontSee('<th>Kontak</th>', false)
            ->getContent();
        $this->assertMatchesRegularExpression('/Belum dinilai|Sudah dinilai/', $html);
    }

    public function test_document_sync_uses_search_sort_status_dropdown_and_page_size(): void
    {
        $this->actingAs($this->admin)->get(route('admin.documents.index', ['per_page' => 25, 'q' => 'Arunika', 'status' => 'menunggu']))->assertOk()
            ->assertSeeInOrder(['name="q"', 'id="urut-dokumen"', 'name="status"'], false)
            ->assertSee('<option value="menunggu" selected', false)
            ->assertSee('id="per-halaman-dokumen"', false)->assertSee('<option value="25" selected', false)
            ->assertDontSee('btn btn-sm btn-primary">Semua', false);
    }

    public function test_recap_columns_include_class_and_component_clo(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.recap.index'))->assertOk()
            ->assertSeeInOrder(['>NIM<', '>Nama<', '>Kelas<', '>Nama Tim<', 'Proposal', '>NA<', '>CLO1<', 'PLO01-CLO10'], false)
            ->assertSee('id="cari-rekap"', false)->assertSee('id="urut-rekap"', false)->assertSee('id="per-halaman-rekap"', false)
            ->getContent();
        $this->assertStringContainsString('colspan=', $html);
    }

    public function test_reminder_menu_sits_above_document_sync(): void
    {
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSeeInOrder(['Tim Mahasiswa', 'Penilaian &amp; Reminder', 'Reminder Otomatis', 'Sinkronisasi Dokumen', 'Raw Data Penilaian', 'Rekap Penilaian'], false);
    }
}
