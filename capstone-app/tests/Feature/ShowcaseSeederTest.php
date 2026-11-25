<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\Team;
use App\Models\User;
use App\Services\ScoreCalculator;
use App\Support\PeriodContext;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Data demo presentasi (ShowcaseSeeder): berjalan di atas data lama dan setiap halaman utama tiap peran berisi data. */
class ShowcaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_showcase_seeds_every_process_and_pages_open(): void
    {
        Storage::fake('local');
        $this->seed();
        $this->seed(DemoSeeder::class);    // data lama tetap ada
        $this->seed(ShowcaseSeeder::class);
        $this->withoutVite();

        $period = Period::query()->where('name', ShowcaseSeeder::PERIOD_NAME)->firstOrFail();
        $this->assertTrue(Period::query()->where('name', '!=', ShowcaseSeeder::PERIOD_NAME)->where('is_active', true)->exists(), 'periode lama tetap aktif');

        // Rekap: dua tim lengkap dengan grade A dan B
        PeriodContext::using($period, function () use ($period) {
            $grades = Team::query()->where('period_id', $period->id)->whereIn('name', ['Tim Nusantara', 'Tim Cakrawala'])->orderBy('name')->get()
                ->map(fn (Team $t) => app(ScoreCalculator::class)->forTeam($t))->map(fn ($r) => [$r['complete'], $r['rows']->first()['grade']])->all();
            $this->assertSame([[true, 'B'], [true, 'A']], $grades);
        });

        $prodi = User::query()->where('email', 'prodi@demo.test')->firstOrFail();
        foreach (['admin.dashboard', 'admin.teams.index', 'admin.topics.index', 'admin.lecturer-topics.index', 'admin.quotas.index', 'admin.plotting.index',
            'admin.monitoring.progress', 'admin.monitoring.assessments', 'admin.reminders.index', 'admin.documents.index', 'admin.recap.index', 'admin.raw-scores.index',
            'admin.students.index', 'admin.lecturers.index', 'admin.master-data.index', 'admin.indicators.index', 'admin.files.index'] as $route) {
            $this->actingAs($prodi)->get(route($route))->assertOk();
        }
        $this->actingAs($prodi)->get(route('admin.teams.index'))->assertSee('Tim Bahari')->assertSee('Tim Gemilang');
        $this->actingAs($prodi)->get(route('admin.master-data.index'))->assertSee('Tim Nusantara')->assertSee('Log Registrasi Tim');
        $this->actingAs($prodi)->get(route('admin.recap.index'))->assertSee('Tim Nusantara');

        $lecturer = fn (string $key) => User::query()->where('email', "{$key}@demo.test")->firstOrFail();
        foreach (['andi', 'rina', 'dewi', 'budi'] as $key) {
            foreach (['dosen.dashboard', 'dosen.assessments.index'] as $route) {
                $this->actingAs($lecturer($key))->get(route($route))->assertOk();
            }
        }
        $this->actingAs($lecturer('rina'))->get(route('dosen.logbooks.index'))->assertOk();
        $this->actingAs($lecturer('fajar'))->get(route('dosen.applications.index'))->assertOk()->assertSee('Platform pelaporan keuangan UMKM');
        $this->actingAs($lecturer('sari'))->get(route('dosen.dashboard'))->assertOk()->assertSee('password');

        $student = fn (int $no) => User::query()->where('email', sprintf('mhs%02d@demo.test', $no))->firstOrFail();
        foreach ([1, 7, 9, 12, 14, 17, 22, 23, 24] as $no) {
            $this->actingAs($student($no))->get(route('mahasiswa.dashboard'))->assertOk();
        }
        foreach (['mahasiswa.documents.index', 'mahasiswa.logbooks.index', 'mahasiswa.assessments.index', 'mahasiswa.peer.index'] as $route) {
            $this->actingAs($student(1))->get(route($route))->assertOk();
        }
    }
}
