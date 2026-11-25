<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use App\Services\ScoreCalculator;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Master Data: kartu log tiap proses (Cari | Sort | filter | jumlah data per kartu), dan halaman Raw Data Penilaian. */
class ProcessLogsAndRawScoresTest extends TestCase
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

    public function test_student_tab_has_a_card_per_process(): void
    {
        $response = $this->asAdmin()->get(route('admin.master-data.index'))->assertOk()
            ->assertSee('Log Registrasi Tim')->assertSee('Log Pengajuan Topik Mahasiswa')->assertSee('Log Bimbingan')
            ->assertSee('Log Submission Mahasiswa')->assertSee('Log Peer Review')->assertDontSee('Log Perubahan Data Mahasiswa')
            ->assertSee('name="topik_q"', false)->assertSee('name="topik_jenis"', false)->assertSee('name="bimbingan_per"', false)
            ->assertDontSee('Log Status Dosen');

        // Pengajuan topik berisi topik mandiri dan lamaran ke topik dosen
        $topics = $response->viewData('topicLog');
        $this->assertEqualsCanonicalizing(['mandiri', 'dosen'], collect($topics->items())->pluck('type')->unique()->values()->all());
        $this->assertSame(5, $topics->perPage());
        $this->assertSame(1, $response->viewData('submissionLog')->total());
        $this->assertGreaterThan(0, $response->viewData('logbookLog')->total());

        // Log Pengajuan Topik Dosen oleh Mahasiswa: hanya keputusan dosen (diterima / ditolak), filter keputusan sendiri
        $response->assertSee('Log Pengajuan Topik Dosen oleh Mahasiswa')->assertSee('name="lamaran_topik_status"', false);
        $decided = \App\Models\TopicApplication::query()->whereIn('status', ['diterima', 'ditolak'])
            ->whereHas('team', fn ($t) => $t->where('period_id', Period::active()->id))->count();
        $this->assertSame($decided, $response->viewData('lecturerTopicApplicationLog')->total());
        $accepted = $this->asAdmin()->get(route('admin.master-data.index', ['lamaran_topik_status' => 'diterima']))->viewData('lecturerTopicApplicationLog');
        $this->assertSame(['diterima'], collect($accepted->items())->pluck('status')->unique()->values()->all() ?: ['diterima']);
    }

    public function test_each_card_filters_and_pages_on_its_own(): void
    {
        $response = $this->asAdmin()->get(route('admin.master-data.index', ['topik_jenis' => 'dosen', 'bimbingan_per' => 10, 'bimbingan_status' => 'disetujui', 'bimbingan_q' => 'Tim Arunika']));
        $this->assertSame(['dosen'], collect($response->viewData('topicLog')->items())->pluck('type')->unique()->values()->all());
        $logbooks = $response->viewData('logbookLog');
        $this->assertSame(10, $logbooks->perPage());
        $this->assertSame(8, $logbooks->total()); // Tim Arunika: 8 log disetujui
        $this->assertSame(['disetujui'], collect($logbooks->items())->pluck('status')->unique()->values()->all());
        // Kartu lain tidak ikut tersaring; filter kartu lain tetap terbawa di form kartu ini
        $this->assertSame(5, $response->viewData('submissionLog')->perPage());
        $response->assertSee('name="topik_jenis" value="dosen"', false);

        // Urutan: Sesi ke naik
        $sorted = $this->asAdmin()->get(route('admin.master-data.index', ['bimbingan_q' => 'Tim Arunika', 'bimbingan_urut' => 'sesi', 'bimbingan_arah' => 'asc']))->viewData('logbookLog');
        $this->assertSame(1, $sorted->items()[0]['session']);
    }

    public function test_lecturer_tab_cards(): void
    {
        $response = $this->asAdmin()->get(route('admin.master-data.index', ['tab' => 'dosen']))->assertOk()
            ->assertSee('Log Status Dosen')->assertSee('Log Pengajuan Topik Dosen')->assertSee('Log Penilaian')
            ->assertDontSee('Log Registrasi Tim');
        $this->assertGreaterThan(0, $response->viewData('assignmentLog')->total());
        $this->assertGreaterThan(0, $response->viewData('lecturerTopicLog')->total());

        $assessments = $this->asAdmin()->get(route('admin.master-data.index', ['tab' => 'dosen', 'penilaian_bentuk' => 'proposal']))->viewData('assessmentLog');
        $this->assertSame(Assessment::query()->where('component', 'proposal')->count(), $assessments->total());
        $this->assertSame(['proposal'], collect($assessments->items())->pluck('component')->unique()->values()->all());
    }

    public function test_lecturer_assessment_table_shows_total_score(): void
    {
        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $lecturer = $team->supervisor->lecturer;
        $response = $this->actingAs($lecturer)->get(route('dosen.assessments.index', ['komponen' => 'proposal']))->assertOk()
            ->assertSee('>Nilai</th>', false);
        // Skala 0–100 (poin ÷ total bobot bentuk penilaian × 100), sama dengan tampilan mahasiswa
        $summary = app(ScoreCalculator::class)->componentSummary($team)->firstWhere('component', 'proposal');
        $expected = round($summary['points'] / $summary['max'] * 100, 2);
        $this->assertEqualsWithDelta($expected, $response->viewData('scores')[$team->id], 0.01);
        $response->assertSee(number_format($expected, 2, ',', ''))->assertSee('&nbsp;/ 100', false);
    }

    public function test_raw_scores_page_breaks_down_each_clo(): void
    {
        $this->asAdmin()->get(route('admin.raw-scores.index', ['tampilan' => 'clo']))->assertOk()
            ->assertSee('Raw Data Penilaian')->assertSee('Total Assessment')->assertSee('Lisan')->assertSee('Total CLO 1')
            ->assertSee('Tim Arunika')->assertSee('Teman 1')->assertSee('PLO01-CLO10');

        $calculator = app(ScoreCalculator::class);
        // Kolom Lisan hanya untuk CLO yang indikatornya memakai Faktor Lisan: Pameran (skema Dokumen) tanpa Lisan
        $columns = $calculator->rawColumns();
        $this->assertSame([false], array_values(array_unique($columns['pameran']['oral'])));
        $this->assertTrue($columns['proposal']['oral']['CLO1']);
        $team = Team::query()->where('name', 'Tim Arunika')->first();
        $row = $calculator->rawForTeam($team)->first();
        $proposal = $row['components']['proposal'];
        $this->assertTrue($proposal['ready']);
        // Total CLO = CLO × Lisan, dan Total Assessment sama dengan poin proposal di ringkasan penilaian
        foreach ($proposal['clos'] as $cell) {
            $this->assertEqualsWithDelta($cell['base'] * $cell['lisan'], $cell['total'], 0.0001);
        }
        $summary = $calculator->componentSummary($team)->firstWhere('component', 'proposal');
        $this->assertEqualsWithDelta($summary['points'], $proposal['total'], 0.01);
        $this->assertFalse($row['components']['laporan_progress']['ready']);
        $this->assertSame(['CLO1', 'CLO2'], $calculator->rawColumns()['proposal']['clos']);
    }
}
