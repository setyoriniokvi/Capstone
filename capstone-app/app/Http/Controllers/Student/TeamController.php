<?php

namespace App\Http\Controllers\Student;

use App\Services\ScreeningService;
use App\Services\TeamRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * F21 / F24 — halaman Tim Saya. Mahasiswa yang sudah punya akun tetapi belum bertim (mis. timnya terhapus admin)
 * dapat mendaftarkan tim lewat tombol "Registrasi Tim" — formulir sama dengan registrasi publik, identitas ketua
 * diambil dari akunnya. Tim yang sudah terdaftar hanya dapat diubah admin.
 */
class TeamController extends StudentController
{
    public function show(Request $request, ScreeningService $screening, TeamRegistration $registration): View
    {
        $user = $request->user();
        $period = $this->period()->load('registrationRule.courses');
        $team = $user->teamFor($period)?->load(['members.user', 'leader']);

        return view('mahasiswa.team', [
            'period' => $period,
            'team' => $team,
            'members' => $team?->members->where('invite_status', 'diterima')->values() ?? collect(),
            'screening' => $team ? $screening->summary($team) : null,
            'eligibility' => $screening->eligibility($user, $period->registrationRule),
            // Pilihan invite anggota hanya dimuat bila formulir dapat dibuka (belum bertim).
            'students' => $team ? collect() : $registration->availableStudents($period, $user),
        ] + $registration->options($period));
    }

    public function store(Request $request, TeamRegistration $registration): RedirectResponse
    {
        $user = $request->user();
        $period = $this->period();
        $this->ensureWindow('registrasi_tim', 'Registrasi tim');
        if ($user->teamFor($period)) {
            throw ValidationException::withMessages(['team' => 'Anda sudah tergabung dalam tim pada periode ini.']);
        }

        $data = $registration->validate($request, $period, $user);
        $team = $registration->save($period, $data, $request);
        $result = $registration->submitted($team, $user);

        return redirect()->route('mahasiswa.team.show')
            ->with('status', 'Registrasi tim diajukan ke admin. Skor screening otomatis: '.$result['score'].'/100.');
    }
}
