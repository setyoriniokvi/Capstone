<?php

namespace Tests\Feature;

use App\Models\AssessmentComponent;
use App\Models\Milestone;
use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use App\Support\PeriodContext;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penghubung deliverable ↔ bentuk penilaian: admin memilih Jenis dokumen (boleh lebih dari satu) pada Jadwal pengisian
 * nilai dosen; tombol "Nilai" di Deliverable detail tim dosen mengikuti pilihan itu (bukan pemetaan hardcode).
 */
class DocumentAssessmentLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
    }

    public function test_default_components_keep_previous_mapping(): void
    {
        $this->assertSame('proposal', AssessmentComponent::forDocument('proposal', 'pembimbing'));
        $this->assertSame('laporan_progress', AssessmentComponent::forDocument('laporan_progress', 'pembimbing'));
        $this->assertSame('sidang_pembimbing', AssessmentComponent::forDocument('laporan_akhir', 'pembimbing'));
        $this->assertSame('sidang_penguji', AssessmentComponent::forDocument('materi_presentasi', 'penguji'));
        $this->assertSame('pameran', AssessmentComponent::forDocument('poster', 'penguji'));
        $this->assertNull(AssessmentComponent::forDocument('proposal', 'penguji'));
        $this->assertNull(AssessmentComponent::forDocument('lainnya', 'pembimbing'));
    }

    public function test_admin_links_document_types_and_nilai_button_follows(): void
    {
        $period = Period::active();
        $admin = User::query()->where('role', 'admin')->firstOrFail();
        $edit = $this->actingAs($admin)->get(route('admin.periods.edit', $period))->assertOk()
            ->assertSee('Jenis dokumen')->assertSee('name="document_types[]"', false);

        // Bentuk penilaian baru menilai Laporan Progress (lebih dari satu jenis boleh dipilih)
        $this->actingAs($admin)->post(route('admin.periods.components.store', $period), [
            'label' => 'Seminar Kemajuan', 'roles' => ['penguji'], 'document_types' => ['laporan_progress', 'proposal'],
        ])->assertSessionHasNoErrors();
        $component = PeriodContext::using($period, fn () => AssessmentComponent::query()->where('label', 'Seminar Kemajuan')->firstOrFail());
        $this->assertSame(['proposal', 'laporan_progress'], $component->document_types);
        $this->actingAs($admin)->post(route('admin.periods.components.store', $period), [
            'label' => 'Salah', 'roles' => ['penguji'], 'document_types' => ['bukan_jenis'],
        ])->assertSessionHasErrors('document_types.0');

        // Penguji Tim Arunika: tombol Nilai Laporan Progress kini mengarah ke Seminar Kemajuan
        $team = Team::query()->where('name', 'Tim Arunika')->firstOrFail();
        $examiner = $team->examiner->lecturer;
        $this->assertTrue(Milestone::query()->where('period_id', $period->id)->where('document_type', 'laporan_progress')->exists());
        $this->actingAs($examiner)->get(route('dosen.teams.examined', ['tim' => $team->id]))->assertOk()
            ->assertSee(route('dosen.assessments.edit', [$team, $component->key]), false);

        // Lepas semua jenis dokumen dari Proposal → tombol Nilai Proposal pembimbing nonaktif
        $proposal = PeriodContext::using($period, fn () => AssessmentComponent::query()->where('key', 'proposal')->firstOrFail());
        $this->actingAs($admin)->put(route('admin.periods.components.update', [$period, $proposal]), [
            '_component' => (string) $proposal->id, 'label' => $proposal->label, 'roles' => $proposal->roles, 'document_types' => [],
        ])->assertSessionHasNoErrors();
        $this->assertSame([], $proposal->fresh()->document_types);
        $supervisor = $team->supervisor->lecturer;
        $this->actingAs($supervisor)->get(route('dosen.teams.supervised', ['tim' => $team->id]))->assertOk()
            ->assertDontSee(route('dosen.assessments.edit', [$team, 'proposal']), false);
    }
}
