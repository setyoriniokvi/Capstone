<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Clo;
use App\Models\PeerReview;
use App\Models\Period;
use App\Models\ScoreAdjustment;
use App\Models\Team;
use App\Models\User;
use App\Services\RecapExporter;
use App\Services\ScoreCalculator;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Revisi penilaian (bimbingan Bu Syfa + sheet "Indikator Draft -> Igadis"): kode CLO iGadis, nilai level rubrik
 * (95/80/70/50/40/0), Raw Data per indikator, Rekap Penilaian, dua skenario form dosen, penilaian setelah tenggat,
 * Penyesuaian Nilai pembimbing, dan Rekap Nilai Kelas dosen kelas.
 */
class AssessmentRevisionTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
        $this->team = Team::query()->where('name', 'Tim Arunika')->firstOrFail();
    }

    private function asAdmin(): static
    {
        return $this->actingAs(User::query()->where('role', 'admin')->first())->withSession([Period::WORKSPACE_KEY => Period::active()->id]);
    }

    /** Semua bentuk penilaian dosen final (tingkat 4) + peer review lengkap (nilai 75). */
    private function completeTeam(): void
    {
        $calculator = app(ScoreCalculator::class);
        Assessment::query()->where('team_id', $this->team->id)->delete();
        foreach ($calculator->lecturerComponents() as $component) {
            $assessment = Assessment::create(['team_id' => $this->team->id, 'assessor_id' => $this->team->supervisor->lecturer_id, 'component' => $component,
                'assessor_role' => config("capstone.components.{$component}.role"), 'status' => 'final', 'submitted_at' => now()]);
            foreach ($calculator->indicatorsFor($component) as $indicator) {
                $assessment->scores()->create(['indicator_id' => $indicator->id, 'score' => 4, 'factor' => 1]);
            }
        }
        $members = $this->team->activeUsers()->get();
        foreach ($members as $reviewer) {
            foreach ($members->whereNotIn('id', [$reviewer->id]) as $reviewee) {
                foreach (array_keys(config('capstone.peer_aspects')) as $aspect) {
                    PeerReview::query()->updateOrCreate(['reviewer_id' => $reviewer->id, 'reviewee_id' => $reviewee->id, 'aspect' => $aspect],
                        ['team_id' => $this->team->id, 'score' => 75]);
                }
            }
        }
    }

    public function test_clo_igadis_codes_are_mapped_and_editable(): void
    {
        $this->assertSame(Clo::IGADIS_DEFAULTS, Clo::query()->whereIn('code', array_keys(Clo::IGADIS_DEFAULTS))->pluck('igadis_code', 'code')->sortKeys()->all());
        $this->asAdmin()->get(route('admin.clos.index'))->assertOk()->assertSee('CLO iGadis')->assertSee('PLO02-CLO05')->assertSee('PLO05-CLO05');

        $clo = Clo::query()->where('code', 'CLO1')->first();
        $this->asAdmin()->put(route('admin.clos.update-many'), ['rows' => [$clo->id => [
            'code' => 'CLO1', 'igadis_code' => 'plo01-clo11', 'description' => $clo->description, 'assessment_scheme' => $clo->assessment_scheme,
            'weights' => $clo->componentWeights->pluck('weight', 'component')->all(),
        ]]])->assertSessionHasNoErrors();
        $this->assertSame('PLO01-CLO11', $clo->fresh()->igadis_code);
    }

    public function test_indicator_value_uses_rubric_level_values(): void
    {
        $calculator = app(ScoreCalculator::class);
        $indicator = $calculator->indicatorsFor('proposal')->firstWhere('code', '1.1'); // bobot proposal 2
        foreach ([5 => 1.9, 4 => 1.6, 3 => 1.4, 2 => 1.0, 1 => 0.8, 0 => 0.0] as $level => $value) {
            $this->assertEqualsWithDelta($value, $calculator->indicatorValue($indicator, 'proposal', $level), 0.0001);
        }
        $this->asAdmin()->get(route('admin.indicators.index'))->assertOk()->assertSee('Nilai level ÷ 100')->assertSee('Tampilan form penilaian dosen');
    }

    public function test_admin_menu_orders_raw_data_before_recap_and_raw_shows_indicators(): void
    {
        $this->asAdmin()->get(route('admin.dashboard'))->assertSeeInOrder(['Raw Data Penilaian', 'Rekap Penilaian']);

        // Dua tab: Per Assessment/CLO (bawaan, semua assessment sekaligus) | Per Indikator; tanpa dropdown assessment
        $this->asAdmin()->get(route('admin.raw-scores.index'))->assertOk()->assertSeeInOrder(['Per Assessment/CLO', 'Per Indikator'])
            ->assertDontSee('Jenis assessment')->assertSeeInOrder(['Proposal', 'Laporan Progress', 'Peer Review', 'Pameran'])
            ->assertSee('CLO 1')->assertSee('PLO01-CLO10')->assertSee('Teman 1')->assertSee('Teman 2');
        // Per Indikator: judul CLO, kolom sesuai skema; peer review per teman (bukan Lisan)
        $combined = $this->asAdmin()->get(route('admin.raw-scores.index', ['tampilan' => 'indikator']))->assertOk()
            ->assertSee('CLO 1')->assertSee('CLO 8')->assertSee('Dokumen')->assertSee('Nilai')->assertSee('1.1')->assertSee('Teman 1');
        $this->assertTrue($combined->viewData('groups')->flatten()->contains(fn ($i) => $i->scheme === 'dokumen'));
        $calculator = app(ScoreCalculator::class);
        $row = $calculator->rawIndicatorsForTeam($this->team, 'proposal')->first();
        $this->assertTrue($row['ready']);
        foreach ($row['cells'] as $cell) {
            $this->assertEqualsWithDelta($cell['doc'], \App\Models\AssessmentComponent::levelValue($cell['level']), 0.0001);
        }
        // Total per indikator sama dengan total assessment rangkuman CLO
        $this->assertEqualsWithDelta($calculator->rawForTeam($this->team)->first()['components']['proposal']['total'], $row['total'], 0.0001);

        // Tautan lama ?bentuk= diarahkan ke Per Assessment/CLO
        $this->asAdmin()->get(route('admin.raw-scores.index', ['bentuk' => 'peer_review']))->assertOk()->assertSee('Teman 1')->assertDontSee('Rata-rata nilai');
    }

    public function test_form_scenarios_follow_period_setting(): void
    {
        $lecturer = $this->team->supervisor->lecturer;
        $edit = route('dosen.assessments.edit', [$this->team, 'proposal']);

        // Skenario 2 (bawaan): centang Dokumen & Lisan terpisah, kode CLO iGadis di judul CLO
        $this->actingAs($lecturer)->get($edit)->assertOk()
            ->assertSee('data-kind="doc"', false)->assertSee('data-kind="oral"', false)->assertDontSee('data-kind="both"', false)
            ->assertSee('(PLO01-CLO10)')->assertSee('data-mode="split"', false);

        // Skenario 1: satu centang, CLO berurutan
        $this->asAdmin()->put(route('admin.indicators.form-mode'), ['assessment_form_mode' => 'sequential'])->assertSessionHasNoErrors();
        $this->assertSame('sequential', Period::active()->fresh()->formMode());
        $this->actingAs($lecturer)->get($edit)->assertOk()
            ->assertSee('data-kind="both"', false)->assertSee('data-group-lock', false)->assertSee('data-mode="sequential"', false);

        $this->asAdmin()->put(route('admin.indicators.form-mode'), ['assessment_form_mode' => 'lain'])->assertSessionHasErrors('assessment_form_mode');
    }

    public function test_assessment_stays_open_after_deadline(): void
    {
        $lecturer = $this->team->supervisor->lecturer;
        $indicator = app(ScoreCalculator::class)->indicatorsFor('proposal')->first();
        $route = route('dosen.assessments.scores', [$this->team, 'proposal']);
        $windows = $this->team->period->assessmentWindows()->where('component', 'proposal');

        $windows->update(['opens_at' => now()->subWeeks(2), 'due_at' => now()->subDay()]);
        $this->actingAs($lecturer)->get(route('dosen.assessments.edit', [$this->team, 'proposal']))->assertOk()->assertSee('Tenggat penilaian sudah lewat');
        $this->actingAs($lecturer)->put($route, ['scores' => [$indicator->id => ['score' => 3, 'oral_level' => 4]]])->assertSessionHasNoErrors();

        $windows->update(['opens_at' => now()->addDay(), 'due_at' => now()->addWeek()]);
        $this->actingAs($lecturer)->put($route, ['scores' => [$indicator->id => ['score' => 3, 'oral_level' => 4]]])->assertSessionHasErrors('window');
    }

    public function test_supervisor_adjusts_final_score_and_igadis_scales(): void
    {
        $supervisor = $this->team->supervisor->lecturer;
        $student = $this->team->activeUsers()->orderBy('name')->first();
        $update = route('dosen.adjustments.update', [$this->team, $student]);

        // Belum lengkap → ditolak; halaman tetap menampilkan mahasiswanya (isian mati)
        $this->actingAs($supervisor)->get(route('dosen.adjustments.index'))->assertOk()->assertSee($student->name)->assertSee('Nilai final')
            ->assertDontSee('Alasan')->assertDontSee('>Hapus<', false)->assertSee('data-sort-nama', false);
        $this->actingAs($supervisor)->put($update, ['score' => 60])->assertSessionHasErrors('score');

        $this->completeTeam();
        $calculator = new ScoreCalculator;
        $system = $calculator->forTeam($this->team->fresh())['rows']->firstWhere('student.id', $student->id)['system_total'];
        $this->actingAs($supervisor)->put($update, ['score' => 120])->assertSessionHasErrors('score');
        $this->actingAs($supervisor)->put($update, ['score' => 60])->assertSessionHasNoErrors();
        $this->assertSame($supervisor->id, ScoreAdjustment::query()->value('lecturer_id'));

        $result = (new ScoreCalculator)->forTeam($this->team->fresh());
        $row = $result['rows']->firstWhere('student.id', $student->id);
        $this->assertEqualsWithDelta(60.0, $row['total'], 0.001);
        $this->assertEqualsWithDelta($system, $row['system_total'], 0.001);
        $this->assertEqualsWithDelta(60 / $system, $row['scale'], 0.0001);
        $this->assertSame((new ScoreCalculator)->grade(60), $row['grade']);
        // Anggota lain tetap nilai sistem
        $this->assertNull($result['rows']->firstWhere('student.id', '!=', $student->id)['adjusted']);

        // Ekspor iGadis: kolom diskalakan proporsional → Σ nilai × bobot = 60
        $exporter = app(RecapExporter::class);
        $total = collect($exporter->igadisColumns())->sum(fn ($c) => ($exporter->cellValue($result, $row, $c)['value'] ?? 0) * $c['weight'] / 100);
        $this->assertEqualsWithDelta(60.0, $total, 0.05);
        $path = $exporter->toIgadis(collect([['team' => $this->team] + $result]));
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);
        $line = collect(range(5, $sheet->getHighestRow()))->first(fn ($r) => (string) $sheet->getCell("D{$r}")->getValue() === (string) $student->nim);
        $this->assertEqualsWithDelta(60.0, $sheet->getCell($sheet->getHighestColumn().$line)->getCalculatedValue(), 0.05);

        // Rekap admin menandai penyesuaian; mahasiswa tidak melihatnya
        $this->asAdmin()->get(route('admin.recap.index'))->assertOk()->assertSee('Disesuaikan');
        $this->actingAs($student)->get(route('mahasiswa.assessments.index'))->assertOk()->assertDontSee('Disesuaikan');

        // Penguji tidak boleh; hapus = kembali ke nilai sistem
        $examiner = $this->team->examiner?->lecturer;
        if ($examiner && $examiner->id !== $supervisor->id) {
            $this->actingAs($examiner)->put($update, ['score' => 10])->assertForbidden();
        }
        // Simpan dengan isian kosong → nilai final kembali ke nilai sistem
        $this->actingAs($supervisor)->put($update, ['score' => ''])->assertSessionHasNoErrors();
        $this->assertSame(0, ScoreAdjustment::query()->count());
        $this->assertNull((new ScoreCalculator)->forTeam($this->team->fresh())['rows']->firstWhere('student.id', $student->id)['adjusted']);
    }

    public function test_class_lecturer_picks_class_then_exports_igadis(): void
    {
        $lecturer = User::query()->where('email', 'dosen@gmail.com')->firstOrFail();
        $this->actingAs($lecturer)->get(route('dosen.recap.index'))->assertForbidden();

        $lecturer->lecturerProfile->update(['is_class_lecturer' => true]);
        // Satu anggota di kelas SI-47-99, anggota lain di kelas lain
        $members = $this->team->activeUsers()->orderBy('name')->get();
        $student = $members->first();
        $student->update(['class_name' => 'SI-47-99']);
        $members->skip(1)->each->update(['class_name' => 'SI-47-98']);
        $class = 'SI-47-99';

        $this->actingAs($lecturer)->get(route('dosen.dashboard'))->assertSee('Rekap Nilai Kelas');
        $this->actingAs($lecturer)->get(route('dosen.recap.index'))->assertOk()->assertSee('Semua kelas')->assertSee($class)->assertSee('data-filter-kelas="'.$class.'"', false);
        $this->actingAs($lecturer)->get(route('dosen.recap.export', ['mode' => 'igadis']))->assertRedirect(route('dosen.recap.index'));

        // Semua kelas dikirim, pilihan kelas menyaring di browser; tautan ekspor sudah membawa kelas terpilih
        $this->actingAs($lecturer)->get(route('dosen.recap.index', ['kelas' => $class]))->assertOk()->assertSee($student->name)->assertSee('PLO01-CLO10')
            ->assertSee('data-filter-kelas="SI-47-98"', false)->assertSee('kelas='.$class, false)->assertSee('<option value="'.$class.'" selected', false);
        $file = $this->actingAs($lecturer)->get(route('dosen.recap.export', ['mode' => 'igadis', 'kelas' => $class]))->assertOk()->assertDownload()
            ->baseResponse->getFile()->getPathname();
        // Ekspor hanya berisi mahasiswa kelas terpilih
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
        $this->assertSame($class, $sheet->getCell('C5')->getValue());
        $this->assertNull($sheet->getCell('C6')->getValue());
    }

    public function test_oral_rubric_info_on_lecturer_form_follows_admin_rubric(): void
    {
        $period = Period::active();
        $level4 = \App\Support\PeriodContext::using($period, fn () => \App\Models\OralRubricLevel::query()->where('level', 4)->firstOrFail());
        $this->asAdmin()->put(route('admin.oral.update-many'), ['rows' => [
            $level4->id => ['level' => 4, 'factor' => 0.85, 'label' => 'Cukup menguasai', 'behavior' => 'Perilaku baru dari admin'],
        ]])->assertSessionHasNoErrors();

        // Daftar "Indikator Penilaian Lisan" & pilihan kolom Lisan di form dosen langsung memakai rubrik terbaru
        $lecturer = $this->team->supervisor->lecturer;
        $this->actingAs($lecturer)->get(route('dosen.assessments.edit', [$this->team, 'proposal']))->assertOk()
            ->assertSee('Indikator Penilaian Lisan')->assertSee('Cukup menguasai (0,85) - Perilaku baru dari admin')
            ->assertDontSee('Menguasai (0,90)');
    }


    public function test_rubric_levels_all_required_and_related_documents_follow_rubric_sheet(): void
    {
        $period = Period::active();
        [$i11, $i31, $i51, $i63] = \App\Support\PeriodContext::using($period, fn () => collect(['1.1', '3.1', '5.1', '6.3'])
            ->map(fn ($code) => \App\Models\Indicator::query()->where('code', $code)->with('rubric')->firstOrFail())->all());

        // Dokumen terkait = kolom Assessment sheet "Rubrikasi Dokumen v1", diterjemahkan ke jenis dokumen
        // (tanpa Materi Presentasi & Poster: tidak dinilai lewat rubrik dokumen)
        $this->assertSame(['proposal', 'laporan_progress', 'laporan_akhir'], $i11->document_types);
        $this->assertSame(['laporan_akhir'], $i31->document_types);
        $this->assertNull($i51->document_types);
        $this->assertNull($i63->document_types);
        $this->asAdmin()->get(route('admin.rubrics.index'))->assertDontSee('value="materi_presentasi"', false);

        // Semua tingkat 5 … 0 wajib
        $levels = collect(range(0, 5))->mapWithKeys(fn ($l) => ["level_{$l}" => $i11->rubric->{"level_{$l}"}])->all();
        $this->asAdmin()->put(route('admin.rubrics.update-many'), ['rows' => [$i11->id => ['level_3' => ''] + $levels]])
            ->assertSessionHasErrors("rows.{$i11->id}.level_3");
        $this->asAdmin()->get(route('admin.rubrics.index'))->assertOk()->assertDontSee('boleh dikosongkan dari atas');
    }

}
