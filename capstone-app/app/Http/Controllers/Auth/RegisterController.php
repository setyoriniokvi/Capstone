<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Support\PeriodContext;
use Illuminate\Support\Collection;
use App\Services\TeamRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * F21 — registrasi mahasiswa: ketua membuat akunnya sekaligus mendaftarkan tim dan anggota dalam satu formulir.
 * Setelah dikirim tampil halaman konfirmasi; keputusan admin dikirim ke email setiap anggota, dan anggota
 * yang akunnya dibuat ketua mengatur password lewat tautan di email tersebut.
 */
class RegisterController extends Controller
{
    public function show(Request $request, TeamRegistration $registration): View
    {
        $periods = $this->activePeriods();
        $period = $this->chosen($periods, $request->integer('periode'));

        return view('auth.register', [
            'period' => $period,
            'periods' => $periods,
            'blocker' => $registration->windowError($period),
        ] + $registration->options($period));
    }

    public function store(Request $request, TeamRegistration $registration): RedirectResponse
    {
        $period = $this->chosen($this->activePeriods(), $request->integer('period_id'));
        if ($error = $registration->windowError($period)) {
            throw ValidationException::withMessages(['window' => $error]);
        }

        // Data master & keanggotaan mengikuti periode (prodi) yang dipilih pendaftar.
        $team = PeriodContext::using($period, function () use ($registration, $request, $period) {
            $data = $registration->validate($request, $period);

            return $registration->save($period, $data, $request);
        });
        $registration->submitted($team, $team->leader);

        return redirect()->route('register.success')->with('registered', $team->name);
    }

    /** Beberapa periode boleh aktif bersamaan (tiap prodi berjalan paralel): pendaftar memilih periode/prodinya. */
    private function activePeriods(): Collection
    {
        return Period::query()->where('is_active', true)->latest('starts_on')->orderByDesc('id')->get();
    }

    private function chosen(Collection $periods, int $id): ?Period
    {
        return $periods->firstWhere('id', $id) ?? $periods->first();
    }

    /** Halaman konfirmasi setelah registrasi; hanya terbuka tepat setelah formulir berhasil dikirim. */
    public function success(Request $request): View|RedirectResponse
    {
        $team = $request->session()->get('registered');

        return $team ? view('auth.register-success', ['teamName' => $team]) : redirect()->route('register');
    }
}
