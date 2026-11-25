<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Validation\LecturerComponentInput;
use App\Models\AssessmentComponent;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F13 — kartu "Jadwal pengisian nilai dosen" di halaman periode: tambah / ubah / hapus bentuk penilaian dosen beserta
 * penilainya (pembimbing, penguji, atau keduanya) dan jadwal pengisian di periode tersebut (opsional, dapat diatur nanti).
 * Bentuk penilaian berlaku untuk seluruh periode (master data, bobot di CLO & indikator); jadwal tersimpan per periode.
 */
class AssessmentComponentController extends Controller
{
    public function store(Request $request, Period $period): RedirectResponse
    {
        $data = LecturerComponentInput::validate($request->all());

        DB::transaction(function () use ($data, $period) {
            $component = AssessmentComponent::createForLecturers($data);
            $period->syncAssessmentWindow($component->key, $data['opens_at'], $data['due_at']);
        });
        AssessmentComponent::syncConfig();

        return back()->with('status', "Bentuk penilaian {$data['label']} ditambahkan. Atur bobotnya di Master CLO & Indikator agar masuk perhitungan nilai.");
    }

    public function update(Request $request, Period $period, AssessmentComponent $component): RedirectResponse
    {
        $this->ensureLecturerComponent($component);
        $data = LecturerComponentInput::validate($request->all(), $component);

        DB::transaction(function () use ($data, $period, $component) {
            $component->update(['label' => $data['label'], 'roles' => $data['roles'], 'document_types' => $data['document_types']]);
            $period->syncAssessmentWindow($component->key, $data['opens_at'], $data['due_at']);
        });
        AssessmentComponent::syncConfig();

        return back()->with('status', "Bentuk penilaian {$data['label']} diperbarui.");
    }

    public function destroy(Period $period, AssessmentComponent $component): RedirectResponse
    {
        $this->ensureLecturerComponent($component);
        if ($component->hasAssessments()) {
            return back()->withErrors(['component' => "{$component->label} sudah memiliki nilai dari dosen dan tidak dapat dihapus."]);
        }

        DB::transaction(fn () => $component->deleteWithWeights());
        AssessmentComponent::syncConfig();

        return back()->with('status', "Bentuk penilaian {$component->label} dihapus.");
    }

    /** Peer review (diisi mahasiswa) tidak dikelola dari jadwal dosen. */
    private function ensureLecturerComponent(AssessmentComponent $component): void
    {
        if ($component->isPeerReview()) {
            throw ValidationException::withMessages(['component' => 'Peer review tidak dapat diubah dari jadwal penilaian dosen.']);
        }
    }
}
