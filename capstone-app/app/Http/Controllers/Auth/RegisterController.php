<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Period;
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
    public function show(TeamRegistration $registration): View
    {
        $period = Period::active();

        return view('auth.register', [
            'period' => $period,
            'blocker' => $registration->windowError($period),
        ] + $registration->options($period));
    }

    public function store(Request $request, TeamRegistration $registration): RedirectResponse
    {
        $period = Period::active();
        if ($error = $registration->windowError($period)) {
            throw ValidationException::withMessages(['window' => $error]);
        }

        $data = $registration->validate($request, $period);
        $team = $registration->save($period, $data, $request);
        $registration->submitted($team, $team->leader);

        return redirect()->route('register.success')->with('registered', $team->name);
    }

    /** Halaman konfirmasi setelah registrasi; hanya terbuka tepat setelah formulir berhasil dikirim. */
    public function success(Request $request): View|RedirectResponse
    {
        $team = $request->session()->get('registered');

        return $team ? view('auth.register-success', ['teamName' => $team]) : redirect()->route('register');
    }
}
