<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * F1 — login dengan email + password. Akun dibuat admin (impor / input manual);
 * peran diambil dari data akun.
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

        if (! $user || ! $user->password || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        if (! $user->is_active) {
            return back()->withErrors(['email' => 'Akun Anda dinonaktifkan. Hubungi admin Capstone.'])->onlyInput('email');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        Auth::login($user, remember: $request->boolean('remember'));
        $request->session()->regenerate();
        ActivityLog::record('login');

        // F59 — kembali ke halaman yang dituju (mis. halaman penilaian dari QR).
        return redirect()->intended(route($user->homeRoute()));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
