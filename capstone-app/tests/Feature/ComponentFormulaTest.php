<?php

namespace Tests\Feature;

use App\Models\AssessmentComponent;
use App\Models\Indicator;
use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use App\Services\ScoreCalculator;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rumus nilai indikator per bentuk penilaian, disusun admin dari pilihan baku (per periode). */
class ComponentFormulaTest extends TestCase
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

    private function save(array $formulas)
    {
        return $this->actingAs($this->admin)->put(route('admin.indicators.formulas'), ['formula' => $formulas]);
    }

    public function test_default_formula_is_unchanged(): void
    {
        $this->assertSame(AssessmentComponent::FORMULA['divisor'], AssessmentComponent::formulaOf('proposal')['divisor']);
        $this->assertSame('level', AssessmentComponent::formulaOf('proposal')['divisor']);
        $this->assertSame('(Nilai level ÷ 100) × Bobot × Faktor Lisan', AssessmentComponent::formulaText(AssessmentComponent::formulaOf('proposal')));
        $this->actingAs($this->admin)->get(route('admin.indicators.index'))->assertOk()
            ->assertSee('formula[proposal][divisor]', false)->assertSee('Simpan rumus')->assertDontSee('formula[peer_review]', false);
    }

    public function test_admin_changes_formula_and_scores_follow_it(): void
    {
        $calculator = app(ScoreCalculator::class);
        $indicator = Indicator::query()->with('rubric', 'componentWeights')->find($calculator->indicatorsFor('proposal')->first()->id);
        $max = $indicator->maxInterval();
        $weight = $indicator->weightFor('proposal');
        // Bawaan: tingkat 2 = nilai level 50 → (50 ÷ 100) × Bobot × Faktor
        $this->assertEqualsWithDelta(0.5 * $weight * 0.8, $calculator->indicatorValue($indicator, 'proposal', 2, 0.8), 0.0001);

        // Interval ÷ interval maksimum (pilihan lama) tetap tersedia
        $this->save(['proposal' => ['divisor' => 'max', 'fixed' => 5, 'weight' => '1', 'oral' => '1', 'multiplier' => '1']])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta((2 / $max) * $weight * 0.8, $calculator->indicatorValue($indicator, 'proposal', 2, 0.8), 0.0001);

        // Angka tetap 10, tanpa faktor pemahaman, pengali 2 → (2 ÷ 10) × Bobot × 2
        $this->save(['proposal' => ['divisor' => 'fixed', 'fixed' => 10, 'weight' => '1', 'oral' => '0', 'multiplier' => '2']])
            ->assertSessionHasNoErrors();
        $this->assertSame('(Interval ÷ 10) × Bobot × 2', AssessmentComponent::formulaText(AssessmentComponent::formulaOf('proposal')));
        $this->assertEqualsWithDelta((2 / 10) * $weight * 2, $calculator->indicatorValue($indicator, 'proposal', 2, 0.8), 0.0001);
        $this->assertFalse($indicator->usesOralFactor('proposal'));

        // Bentuk penilaian lain tidak terpengaruh
        $this->assertSame(AssessmentComponent::FORMULA['divisor'], AssessmentComponent::formulaOf('laporan_progress')['divisor']);

        // Halaman penilaian dosen tidak menampilkan rumus (cukup topik, jenis & objek topik, anggota)
        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $this->actingAs($team->supervisor->lecturer)->get(route('dosen.assessments.edit', [$team, 'proposal']))->assertOk()
            ->assertDontSee('(Interval ÷ 10) × Bobot × 2')->assertSee('Objek topik')->assertSee($team->finalTopic->source === 'mandiri' ? 'Topik Mandiri' : 'Topik Dosen');
    }

    public function test_formula_validation(): void
    {
        $this->save(['proposal' => ['divisor' => 'fixed', 'fixed' => '', 'weight' => '1', 'oral' => '1', 'multiplier' => '0']])
            ->assertSessionHasErrors(['formula.proposal.fixed', 'formula.proposal.multiplier']);
        $this->save(['proposal' => ['divisor' => 'lain', 'weight' => '1', 'oral' => '1', 'multiplier' => '1']])
            ->assertSessionHasErrors('formula.proposal.divisor');
    }
}
