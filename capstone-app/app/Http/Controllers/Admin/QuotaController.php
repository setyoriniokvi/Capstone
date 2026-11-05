<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoadQuota;
use App\Models\Period;
use App\Models\User;
use App\Services\LoadBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/**
 * F9 — kuota beban dosen: global (semua dosen) atau per dosen (prioritas lebih tinggi). Daftar aturan di halaman utama;
 * "Tambah Kuota" membuka modal — cakupan dosen tertentu dapat memilih beberapa dosen sekaligus (cari nama/NIP/peminatan).
 * Di bawahnya kartu daftar dosen beserta kuota yang berlaku; Edit = buat/ubah aturan per dosen, Reset = kuota dosen itu 0.
 */
class QuotaController extends Controller
{
    public const FIELDS = ['supervise_min', 'supervise_max', 'examine_min', 'examine_max'];

    public function index(LoadBalanceService $balance): View
    {
        $period = Period::workspace();
        $quotas = $period ? LoadQuota::query()->where('period_id', $period->id)->with('lecturer.lecturerProfile')
            ->get()->sortBy(fn (LoadQuota $q) => [$q->scope === 'global' ? 0 : 1, $q->lecturer?->name])->values() : collect();
        $lecturers = User::query()->lecturers()->where('is_active', true)->with('lecturerProfile')->orderBy('name')->get();

        return view('admin.quotas', [
            'period' => $period,
            'quotas' => $quotas,
            'lecturers' => $lecturers,
            // Daftar dosen: kuota yang berlaku (per dosen bila ada, selain itu global) + aturan per dosennya (untuk Reset).
            'rows' => $period ? $lecturers->map(fn (User $lecturer) => [
                'lecturer' => $lecturer,
                'quota' => $balance->quotaFor($lecturer, $period),
                'own' => $quotas->first(fn (LoadQuota $q) => $q->scope === 'dosen' && $q->lecturer_id === $lecturer->id),
            ]) : collect(),
            'specializations' => $lecturers->pluck('lecturerProfile.specialization')->filter()->unique()->sort()->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $period = Period::workspace() ?? abort(409, 'Buka dashboard periode terlebih dahulu.');
        $data = $this->validated($request, [
            'scope' => ['required', 'in:global,dosen'],
            'lecturer_ids' => ['array', 'required_if:scope,dosen'],
            'lecturer_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->where('role', 'dosen')],
        ]);
        $values = array_intersect_key($data, array_flip(self::FIELDS));

        // Cakupan yang sama menimpa aturan sebelumnya (satu aturan global & satu per dosen).
        $targets = $data['scope'] === 'global' ? [null] : $data['lecturer_ids'];
        foreach ($targets as $lecturerId) {
            LoadQuota::query()->updateOrCreate([
                'period_id' => $period->id,
                'scope' => $data['scope'],
                'category' => null,
                'lecturer_id' => $lecturerId,
            ], $values);
        }

        return back()->with('status', $data['scope'] === 'global' ? 'Kuota global disimpan.' : 'Kuota disimpan untuk '.count($targets).' dosen.');
    }

    public function update(Request $request, LoadQuota $quota): RedirectResponse
    {
        $data = $this->validated($request);
        $quota->update(array_intersect_key($data, array_flip(self::FIELDS)));

        return back()->with('status', 'Aturan kuota diperbarui.');
    }

    /** Reset kuota satu dosen menjadi 0: aturan per dosen yang membuat dosen itu tidak menerima tim bimbingan / uji. */
    public function reset(User $lecturer): RedirectResponse
    {
        $period = Period::workspace() ?? abort(409, 'Buka dashboard periode terlebih dahulu.');
        abort_unless($lecturer->isLecturer(), 404);

        LoadQuota::query()->updateOrCreate(
            ['period_id' => $period->id, 'scope' => 'dosen', 'category' => null, 'lecturer_id' => $lecturer->id],
            array_fill_keys(self::FIELDS, 0)
        );

        return back()->with('status', "Kuota {$lecturer->name} direset menjadi 0.");
    }

    public function destroy(LoadQuota $quota): RedirectResponse
    {
        $quota->delete();

        return back()->with('status', 'Aturan kuota dihapus.');
    }

    /** Kuota pembimbing & penguji: 0–99 dan minimum ≤ maksimum (maksimum 0 = tidak menerima tim). */
    private function validated(Request $request, array $extra = []): array
    {
        $data = $request->validate($extra + array_fill_keys(self::FIELDS, ['required', 'integer', 'between:0,99']), [
            'lecturer_ids.required_if' => 'Pilih minimal satu dosen.',
        ]);

        validator($data)->after(function (Validator $v) use ($data) {
            foreach (['supervise', 'examine'] as $role) {
                if ($data["{$role}_min"] > $data["{$role}_max"]) {
                    $v->errors()->add("{$role}_min", 'Minimum tidak boleh lebih besar dari maksimum.');
                }
            }
        })->validate();

        return $data;
    }
}
