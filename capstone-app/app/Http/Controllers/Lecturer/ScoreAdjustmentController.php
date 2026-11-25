<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\ScoreAdjustment;
use App\Models\Team;
use App\Models\User;
use App\Services\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Penyesuaian Nilai (menu Penilaian dosen, khusus pembimbing): daftar mahasiswa tim bimbingan dengan nilai final;
 * pembimbing dapat mengisi nilai disesuaikan (0–100), mis. untuk anggota yang jarang hadir. Simpan dengan isian kosong =
 * nilai final kembali ke nilai sistem. Berlaku setelah nilai sistem lengkap: menggantikan nilai akhir & indeks di Rekap
 * Penilaian, dan seluruh kolom iGadis mahasiswa itu diskalakan proporsional. Tidak terlihat mahasiswa.
 */
class ScoreAdjustmentController extends Controller
{
    public function index(Request $request, ScoreCalculator $calculator): View
    {
        $period = Period::active();
        $teams = $period ? Team::query()->where('period_id', $period->id)->where('status', 'diterima')
            ->whereHas('lecturers', fn ($q) => $q->where('lecturer_id', $request->user()->id)->where('role', 'pembimbing'))
            ->orderBy('name')->get() : collect();

        return view('dosen.assessments.adjustments', [
            'results' => $teams->map(fn (Team $team) => ['team' => $team] + $calculator->forTeam($team)),
        ]);
    }

    public function update(Request $request, Team $team, User $student, ScoreCalculator $calculator): RedirectResponse
    {
        $this->authorizeStudent($request, $team, $student);
        $data = $request->validate([
            'score' => ['nullable', 'numeric', 'between:0,100'],
        ], [
            'score.numeric' => 'Nilai disesuaikan harus berupa angka.',
            'score.between' => 'Nilai disesuaikan 0–100.',
        ]);
        $result = $calculator->forTeam($team);
        if (! $result['complete']) {
            throw ValidationException::withMessages(['score' => 'Nilai sistem tim ini belum lengkap; penyesuaian dapat diisi setelah semua komponen dinilai.']);
        }
        $system = $result['rows']->first(fn (array $row) => $row['student']->id === $student->id)['system_total'] ?? null;

        // Kosong → nilai final kembali ke nilai sistem
        if (($data['score'] ?? null) === null) {
            $deleted = ScoreAdjustment::query()->where('team_id', $team->id)->where('student_id', $student->id)->delete();
            if ($deleted) {
                ActivityLog::record('reset_score_adjustment', $team, ['student' => $student->nim]);
            }

            return back()->with('status', "Nilai final {$student->name} memakai nilai sistem (".number_format((float) $system, 2, ',', '').').');
        }

        $score = round((float) $data['score'], 2);
        ScoreAdjustment::query()->updateOrCreate(
            ['team_id' => $team->id, 'student_id' => $student->id],
            ['lecturer_id' => $request->user()->id, 'score' => $score, 'reason' => null],
        );
        ActivityLog::record('adjust_score', $team, ['student' => $student->nim, 'system' => $system, 'adjusted' => $score]);

        return back()->with('status', "Nilai final {$student->name} disesuaikan menjadi ".number_format($score, 2, ',', '').'.');
    }

    /** Hanya pembimbing tim (diterima) untuk anggota aktif tim tersebut. */
    private function authorizeStudent(Request $request, Team $team, User $student): void
    {
        abort_unless($team->status === 'diterima' && $team->lecturerRole($request->user()) === 'pembimbing', 403, 'Penyesuaian nilai hanya oleh dosen pembimbing tim.');
        abort_unless($team->activeUsers()->whereKey($student->id)->exists(), 404);
    }
}
