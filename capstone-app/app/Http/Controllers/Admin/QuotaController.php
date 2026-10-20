<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoadQuota;
use App\Models\Period;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

/** F9 — kuota beban dosen: global, kategori, atau per dosen. */
class QuotaController extends Controller
{
    public function index(): View
    {
        $period = Period::workspace();

        return view('admin.quotas', [
            'period' => $period,
            'quotas' => $period ? LoadQuota::query()->where('period_id', $period->id)->with('lecturer')->orderByRaw("case scope when 'global' then 0 when 'kategori' then 1 else 2 end")->get() : collect(),
            'lecturers' => User::query()->lecturers()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $period = Period::workspace() ?? abort(409, 'Buka dashboard periode terlebih dahulu.');
        $fields = ['supervise_default', 'supervise_min', 'supervise_max', 'examine_default', 'examine_min', 'examine_max'];

        $data = $request->validate([
            'scope' => ['required', 'in:global,kategori,dosen'],
            'category' => ['nullable', 'required_if:scope,kategori', Rule::in(array_keys(config('capstone.prodi_categories')))],
            'lecturer_id' => ['nullable', 'required_if:scope,dosen', Rule::exists('users', 'id')->where('role', 'dosen')],
        ] + array_fill_keys($fields, ['required', 'integer', 'between:0,99']));

        validator($data)->after(function (Validator $v) use ($data) {
            foreach (['supervise', 'examine'] as $role) {
                if ($data["{$role}_min"] > $data["{$role}_max"] || $data["{$role}_default"] > $data["{$role}_max"] || $data["{$role}_default"] < $data["{$role}_min"]) {
                    $v->errors()->add("{$role}_default", 'Nilai harus memenuhi minimum ≤ default ≤ maksimum.');
                }
            }
        })->validate();

        LoadQuota::query()->updateOrCreate([
            'period_id' => $period->id,
            'scope' => $data['scope'],
            'category' => $data['scope'] === 'kategori' ? $data['category'] : null,
            'lecturer_id' => $data['scope'] === 'dosen' ? $data['lecturer_id'] : null,
        ], array_intersect_key($data, array_flip($fields)));

        return back()->with('status', 'Kuota beban disimpan.');
    }

    public function destroy(LoadQuota $quota): RedirectResponse
    {
        $quota->delete();

        return back()->with('status', 'Aturan kuota dihapus.');
    }
}
