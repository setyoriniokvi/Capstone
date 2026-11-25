<?php

namespace Tests\Feature;

use App\Models\AssessmentComponent;
use App\Models\AssessmentWindow;
use App\Models\Milestone;
use App\Models\Period;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Periode baru tanpa "simpan dulu": milestone, folder Google Drive Database per milestone dokumen, jadwal penilaian
 * bentuk penilaian salinan, dan bentuk penilaian baru disusun di halaman Periode baru lalu tersimpan sekaligus.
 */
class PeriodDraftTest extends TestCase
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

    private function payload(array $extra = []): array
    {
        return $extra + [
            'name' => 'Capstone Genap', 'academic_year' => '2026/2027', 'semester' => 'genap', 'prodi_name' => 'S1 Sistem Informasi',
            'starts_on' => '2027-02-01', 'ends_on' => '2027-06-30', 'min_approved_logbooks' => 8,
            'rule' => ['min_sks' => 110, 'min_members' => 3, 'max_members' => 5],
        ];
    }

    public function test_create_page_has_draft_sections_without_save_first_notice(): void
    {
        $this->actingAs($this->admin())->get(route('admin.periods.create'))->assertOk()
            ->assertSee('x-data="periodDraft"', false)->assertSee('Tambah Milestone')->assertSee('Tambah Folder')->assertSee('Tambah Jadwal Penilaian')
            ->assertSee('data-source=""', false)->assertSee('windows[proposal][opens_at]', false)
            ->assertDontSee('Simpan periode terlebih dahulu');
    }

    public function test_period_is_created_with_milestones_folders_and_schedules(): void
    {
        $milestones = [
            ['key' => 'd1', 'name' => 'Pengumpulan Proposal', 'category' => 'dokumen', 'document_type' => 'proposal', 'allowed_types' => ['pdf'],
                'starts_at' => '2027-02-15T08:00', 'due_at' => '2027-03-01T23:59', 'description' => ''],
            ['key' => 'd2', 'name' => 'Registrasi Tim', 'category' => 'lainnya', 'kind' => 'registrasi_tim',
                'starts_at' => '2027-02-01T08:00', 'due_at' => '2027-02-10T23:59', 'description' => ''],
        ];
        $folder = 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUv';

        $this->actingAs($this->admin())->post(route('admin.periods.store'), $this->payload([
            'draft_milestones' => json_encode($milestones),
            'drive_folders' => ['d1' => $folder],
            'windows' => ['proposal' => ['opens_at' => '2027-03-02T08:00', 'due_at' => '2027-03-10T23:59']],
            'draft_components' => json_encode([['label' => 'Seminar Kemajuan', 'roles' => ['penguji'], 'opens_at' => '', 'due_at' => '']]),
        ]))->assertSessionHasNoErrors();

        $period = Period::query()->where('name', 'Capstone Genap')->firstOrFail();
        $proposal = Milestone::query()->where('period_id', $period->id)->where('name', 'Pengumpulan Proposal')->firstOrFail();
        $this->assertSame('dokumen', $proposal->kind);
        $this->assertSame('1AbCdEfGhIjKlMnOpQrStUv', $proposal->drive_folder_id);
        $this->assertSame('registrasi_tim', Milestone::query()->where('period_id', $period->id)->where('name', 'Registrasi Tim')->value('kind'));
        $this->assertSame('2027-03-10 23:59', AssessmentWindow::query()->where('period_id', $period->id)->where('component', 'proposal')->firstOrFail()->due_at->format('Y-m-d H:i'));
        $this->assertSame(['penguji'], AssessmentComponent::inPeriod($period)->where('label', 'Seminar Kemajuan')->firstOrFail()->roles);
        // Bentuk penilaian baru hanya di periode baru
        $this->assertFalse(AssessmentComponent::inPeriod(Period::active())->where('label', 'Seminar Kemajuan')->exists());
    }

    public function test_invalid_draft_rolls_back_and_keeps_input(): void
    {
        $milestones = [['key' => 'd1', 'name' => 'Proposal', 'category' => 'dokumen', 'document_type' => 'proposal', 'allowed_types' => ['pdf'],
            'starts_at' => '2027-02-15T08:00', 'due_at' => '2027-03-01T23:59']];

        $this->actingAs($this->admin())->from(route('admin.periods.create'))->post(route('admin.periods.store'), $this->payload([
            'draft_milestones' => json_encode($milestones),
            'drive_folders' => ['d1' => 'bukan tautan'],
        ]))->assertRedirect(route('admin.periods.create'))->assertSessionHasErrors('drive_folders.d1')
            ->assertSessionHasInput('drive_folders', ['d1' => 'bukan tautan']);
        $this->assertFalse(Period::query()->where('name', 'Capstone Genap')->exists());

        $this->actingAs($this->admin())->post(route('admin.periods.store'), $this->payload([
            'draft_milestones' => json_encode([['name' => 'Tanpa tanggal', 'category' => 'dokumen', 'document_type' => 'proposal', 'allowed_types' => ['pdf']]]),
        ]))->assertSessionHasErrors('draft_milestones');
        $this->assertFalse(Period::query()->where('name', 'Capstone Genap')->exists());
    }

    public function test_edit_page_lists_drive_folder_rows_with_milestone_picker(): void
    {
        $period = Period::active();
        $this->actingAs($this->admin())->get(route('admin.periods.edit', $period))->assertOk()
            ->assertSee('x-data="driveFolders"', false)->assertSee('data-folder-row', false)->assertSee('Tambah Folder');
    }
}
