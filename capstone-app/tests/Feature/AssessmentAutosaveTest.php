<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Team;
use App\Services\ScoreCalculator;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Penilaian dosen: setiap perubahan disimpan otomatis sebagai draft (JSON), tanpa tombol Simpan Draft. */
class AssessmentAutosaveTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seed(DemoSeeder::class);
        $this->withoutVite();
        $this->team = Team::query()->where('name', 'Tim Arunika')->first();
        $this->team->period->assessmentWindows()->where('component', 'proposal')->update(['opens_at' => now()->subDay(), 'due_at' => now()->addDay()]);
    }

    private function autosave(array $scores)
    {
        return $this->actingAs($this->team->supervisor->lecturer)
            ->putJson(route('dosen.assessments.scores', [$this->team, 'proposal']), ['autosave' => '1', 'mode' => 'draft', 'scores' => $scores]);
    }

    public function test_change_is_saved_as_draft_and_reopened(): void
    {
        $this->team->assessments()->where('component', 'proposal')->delete();
        $indicators = app(ScoreCalculator::class)->indicatorsFor('proposal');
        $first = $indicators->first();
        $oral = $first->usesOralFactor('proposal') ? 3 : null;

        // Belum ada isian sama sekali → tidak membuat draft kosong
        $this->autosave([$first->id => ['score' => '', 'oral_level' => '']])->assertOk()->assertJson(['status' => 'none']);
        $this->assertSame(0, Assessment::query()->where('team_id', $this->team->id)->where('component', 'proposal')->count());

        // Satu interval diisi → draft tersimpan
        $this->autosave([$first->id => ['score' => '2', 'oral_level' => $oral]])->assertOk()
            ->assertJson(['status' => 'draft', 'total' => $indicators->count()])->assertJsonPath('scored', [$first->id]);
        $assessment = Assessment::query()->where('team_id', $this->team->id)->where('component', 'proposal')->first();
        $this->assertSame('draft', $assessment->status);
        $this->assertSame(2, $assessment->scores()->first()->score);

        // Dibuka lagi: lanjut dari draft (nilai terpilih), tombol Simpan Draft sudah tidak ada
        $this->actingAs($this->team->supervisor->lecturer)->get(route('dosen.assessments.edit', [$this->team, 'proposal']))->assertOk()
            ->assertSee('data-autosave="true"', false)->assertDontSee('Simpan Draft')->assertSee('Submit Final');
    }

    public function test_incomplete_row_is_skipped_and_final_becomes_draft_again(): void
    {
        $indicators = app(ScoreCalculator::class)->indicatorsFor('proposal');
        $oralIndicator = $indicators->first(fn ($i) => $i->usesOralFactor('proposal'));
        $this->assertSame('final', $this->team->assessments()->where('component', 'proposal')->first()->status); // dari DemoSeeder

        // Interval dipilih tetapi pemahaman lisan belum → baris dilewati tanpa galat; penilaian final yang diubah menjadi draft
        $response = $this->autosave([$oralIndicator->id => ['score' => '1', 'oral_level' => '']])->assertOk()
            ->assertJson(['status' => 'draft', 'was_final' => true]);
        $this->assertContains($oralIndicator->id, $response->json('scored'));
        $this->assertSame('draft', $this->team->assessments()->where('component', 'proposal')->first()->status);
    }
}
