<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminPeriod;
use App\Models\ActivityLog;
use App\Models\Period;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * F1 — login dengan email + password. Akun dibuat admin (impor / input manual) atau lewat
 * registrasi tim mahasiswa (F21); peran diambil dari data akun.
 */
class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login', [
            // DATA DEMO — daftar akun dummy di bawah form login. Hapus saat implementasi riil.
            'demoAccounts' => config('capstone.dev_login') ? config('capstone.demo_accounts') : [],
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', Str::lower($credentials['email']))->first();

        // Akun anggota yang dibuat ketua belum punya password sampai tim diterima admin (tautan atur password).
        if ($user?->isStudent() && $user->awaitingActivation()) {
            return back()->withErrors(['email' => 'Akun Anda belum memiliki password. Atur password lewat tautan di email yang dikirim saat tim Anda diterima admin.'])->onlyInput('email');
        }

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        if (! $user->is_active) {
            return back()->withErrors(['email' => 'Akun Anda dinonaktifkan. Hubungi admin Capstone.'])->onlyInput('email');
        }

        // Admin prodi: satu akun dapat dipakai beberapa periode; tanpa periode tidak ada yang dapat dikelola.
        $adminPeriod = $user->isProdiAdmin() ? EnsureAdminPeriod::defaultPeriodFor($user) : null;
        if ($user->isProdiAdmin() && ! $adminPeriod) {
            return back()->withErrors(['email' => 'Akun admin prodi ini belum ditautkan ke periode mana pun. Hubungi admin fakultas.'])->onlyInput('email');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        // Setiap perangkat punya sesinya sendiri, jadi satu akun dapat dipakai di beberapa perangkat sekaligus.
        Auth::login($user, remember: $request->boolean('remember'));
        $request->session()->regenerate();
        if ($adminPeriod) {
            session([Period::WORKSPACE_KEY => $adminPeriod->id]);
        }
        ActivityLog::record('login');

        // F59 — kembali ke halaman yang dituju (mis. halaman penilaian dari QR).
        return redirect()->intended(route($user->homeRoute()));
    }

    public function logout(Request $request): RedirectResponse
    {
        // Hanya perangkat ini: token "ingat saya" tidak diganti sehingga perangkat lain dengan akun yang sama tetap masuk.
        Auth::logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
