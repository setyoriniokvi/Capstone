<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use App\Services\ScoreCalculator;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Interval maksimum = banyaknya poin rubrik indikator; nilai = (interval ÷ interval maksimum) × bobot; jenis dokumen indikator. */
class AssessmentIntervalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
    }

    public function test_four_point_rubric_limits_interval_and_scores_full_weight(): void
    {
        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $lecturer = $team->supervisor->lecturer;
        $team->period->assessmentWindows()->where('component', 'proposal')->update(['opens_at' => now()->subDay(), 'due_at' => now()->addDay()]);
        $team->assessments()->where('component', 'proposal')->delete();

        $indicator = app(ScoreCalculator::class)->indicatorsFor('proposal')->first();
        $indicator->rubric->update(['level_5' => null]);
        $indicator = Indicator::query()->with('rubric', 'componentWeights')->find($indicator->id);
        $this->assertSame(4, $indicator->maxInterval());
        $this->assertSame([1, 2, 3, 4], array_keys($indicator->rubricPoints()));

        $route = route('dosen.assessments.scores', [$team, 'proposal']);
        $this->actingAs($lecturer)->put($route, ['scores' => [$indicator->id => ['score' => 5, 'oral_level' => 5]]])
            ->assertSessionHasErrors('scores.'.$indicator->id.'.score');
        $this->actingAs($lecturer)->put($route, ['scores' => [$indicator->id => ['score' => 4, 'oral_level' => 5]]])->assertSessionHasNoErrors();

        // Rumus bawaan memakai nilai level Rubrik Dokumen: tingkat 4 = 80 → 0,8 × bobot (faktor lisan 1,00)
        $calculator = new ScoreCalculator;
        $this->assertEqualsWithDelta(0.8 * $indicator->weightFor('proposal'), $calculator->indicatorValue($indicator, 'proposal', 4, 1.0), 0.0001);
        $this->actingAs($lecturer)->get(route('dosen.assessments.edit', [$team, 'proposal']))->assertOk()->assertSee('Maks. 4');
    }

    public function test_admin_picks_related_document_from_period_milestones_in_rubric(): void
    {
        $admin = User::query()->where('role', 'admin')->first();
        $period = Period::active();
        $indicator = app(ScoreCalculator::class)->indicatorsFor('proposal')->first();
        $levels = collect(range(0, 5))->mapWithKeys(fn ($l) => ["level_{$l}" => $indicator->rubric->describe($l)])->all();
        $session = [Period::WORKSPACE_KEY => $period->id];

        // Pilihan = jenis dokumen milestone periode (Proposal, Laporan Progress, ...); jenis di luar periode ditolak
        $this->actingAs($admin)->withSession($session)->get(route('admin.rubrics.index'))->assertOk()
            ->assertSee('Dokumen terkait')->assertSee('name="document_types[]" value="laporan_progress"', false)->assertDontSee('name="document_types[]" value="lainnya"', false);
        $this->actingAs($admin)->withSession($session)
            ->put(route('admin.rubrics.update-many'), ['rows' => [$indicator->id => ['document_types' => ['lainnya']] + $levels]])
            ->assertSessionHasErrors('rows.'.$indicator->id.'.document_types.0');
        $this->actingAs($admin)->withSession($session)
            ->put(route('admin.rubrics.update-many'), ['rows' => [$indicator->id => ['document_types' => ['proposal', 'laporan_progress']] + $levels]])
            ->assertSessionHasNoErrors();
        $this->assertSame(['proposal', 'laporan_progress'], $indicator->fresh()->document_types);

        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $this->actingAs($team->supervisor->lecturer)->get(route('dosen.assessments.edit', [$team, 'proposal']))->assertOk()->assertSee('Proposal');
    }
}
