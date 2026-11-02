<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\AssessmentComponent;
use App\Models\AssessmentWindow;
use App\Models\Period;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $period) {
            $component = AssessmentComponent::query()->create([
                'key' => $this->uniqueKey($data['label']),
                'label' => $data['label'],
                'roles' => $data['roles'],
                'sort_order' => (int) AssessmentComponent::query()->max('sort_order') + 1,
            ]);
            $this->syncWindow($period, $component->key, $data);
        });
        AssessmentComponent::syncConfig();

        return back()->with('status', "Bentuk penilaian {$data['label']} ditambahkan. Atur bobotnya di Master CLO & Indikator agar masuk perhitungan nilai.");
    }

    public function update(Request $request, Period $period, AssessmentComponent $component): RedirectResponse
    {
        $this->ensureLecturerComponent($component);
        $data = $this->validated($request, $component);

        DB::transaction(function () use ($data, $period, $component) {
            $component->update(['label' => $data['label'], 'roles' => $data['roles']]);
            $this->syncWindow($period, $component->key, $data);
        });
        AssessmentComponent::syncConfig();

        return back()->with('status', "Bentuk penilaian {$data['label']} diperbarui.");
    }

    public function destroy(Period $period, AssessmentComponent $component): RedirectResponse
    {
        $this->ensureLecturerComponent($component);
        if (Assessment::query()->where('component', $component->key)->exists()) {
            return back()->withErrors(['component' => "{$component->label} sudah memiliki nilai dari dosen dan tidak dapat dihapus."]);
        }

        DB::transaction(function () use ($component) {
            AssessmentWindow::query()->where('component', $component->key)->delete();
            DB::table('indicator_component_weights')->where('component', $component->key)->delete();
            DB::table('clo_component_weights')->where('component', $component->key)->delete();
            $component->delete();
        });
        AssessmentComponent::syncConfig();

        return back()->with('status', "Bentuk penilaian {$component->label} dihapus.");
    }

    private function validated(Request $request, ?AssessmentComponent $component = null): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100', Rule::unique('assessment_components', 'label')->ignore($component)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['distinct', Rule::in(array_keys(AssessmentComponent::LECTURER_ROLES))],
            'opens_at' => ['nullable', 'date', 'required_with:due_at'],
            'due_at' => ['nullable', 'date', 'required_with:opens_at', 'after:opens_at'],
        ], [
            'label.required' => 'Isi bentuk penilaian.',
            'label.unique' => 'Bentuk penilaian dengan nama ini sudah ada.',
            'roles.required' => 'Pilih minimal satu penilai.',
            'opens_at.required_with' => 'Isi waktu dibuka, atau kosongkan keduanya untuk diatur nanti.',
            'due_at.required_with' => 'Isi deadline, atau kosongkan keduanya untuk diatur nanti.',
            'due_at.after' => 'Deadline penilaian harus setelah waktu dibuka.',
        ]);
        // Urutan penilai tetap: pembimbing lalu penguji.
        $data['roles'] = array_values(array_intersect(array_keys(AssessmentComponent::LECTURER_ROLES), $data['roles']));

        return $data;
    }

    /** Jadwal hanya disimpan bila waktu dibuka & deadline diisi; dikosongkan → jadwal periode ini dihapus. */
    private function syncWindow(Period $period, string $key, array $data): void
    {
        if (! empty($data['opens_at']) && ! empty($data['due_at'])) {
            $period->assessmentWindows()->updateOrCreate(['component' => $key], ['opens_at' => $data['opens_at'], 'due_at' => $data['due_at']]);
        } else {
            $period->assessmentWindows()->where('component', $key)->delete();
        }
    }

    private function uniqueKey(string $label): string
    {
        $base = Str::limit(Str::slug($label, '_'), 24, '') ?: 'penilaian';
        $key = $base;
        for ($i = 2; AssessmentComponent::query()->where('key', $key)->exists(); $i++) {
            $key = "{$base}_{$i}";
        }

        return $key;
    }

    /** Peer review (diisi mahasiswa) tidak dikelola dari jadwal dosen. */
    private function ensureLecturerComponent(AssessmentComponent $component): void
    {
        if (in_array('mahasiswa', $component->roles, true)) {
            throw ValidationException::withMessages(['component' => 'Peer review tidak dapat diubah dari jadwal penilaian dosen.']);
        }
    }
}
