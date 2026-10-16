<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * F1 — login dengan akun Google. Hanya email yang sudah didaftarkan admin
 * (impor / input manual) yang dapat masuk; peran diambil dari data akun.
 */
class LoginController extends Controller
{
    public function show(): View
    {
        $devUsers = config('capstone.dev_login') && app()->environment('local')
            ? User::query()->where('is_active', true)->orderBy('role')->orderBy('name')->limit(40)->get()
            : collect();

        return view('auth.login', [
            'googleReady' => filled(config('services.google.client_id')),
            'devUsers' => $devUsers,
        ]);
    }

    public function redirect(): RedirectResponse
    {
        if (blank(config('services.google.client_id'))) {
            return redirect()->route('login')->withErrors(['email' => 'Login Google belum dikonfigurasi. Isi GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET.']);
        }

        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'Login Google dibatalkan atau kedaluwarsa. Silakan coba lagi.']);
        }

        $email = Str::lower((string) $google->getEmail());
        $verified = (bool) ($google->user['email_verified'] ?? $google->user['verified_email'] ?? false);
        $domains = config('services.google.allowed_domains');

        if (! $verified || ($domains && ! in_array(Str::after($email, '@'), $domains, true))) {
            return redirect()->route('login')->withErrors(['email' => 'Gunakan akun Google yang terverifikasi dan diizinkan untuk Capstone.']);
        }

        $user = User::query()->where('email', $email)->first();

        // Admin awal ditentukan lewat ADMIN_EMAILS agar sistem dapat dipakai pertama kali.
        if (! $user && in_array($email, config('capstone.admin_emails'), true)) {
            $user = User::create(['email' => $email, 'name' => $google->getName() ?: $email, 'role' => 'admin']);
        }

        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => "Email {$email} belum terdaftar di sistem Capstone. Hubungi admin program studi."]);
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors(['email' => 'Akun Anda dinonaktifkan. Hubungi admin Capstone.']);
        }

        if ($user->google_id && $user->google_id !== $google->getId()) {
            return redirect()->route('login')->withErrors(['email' => 'Akun Google tidak cocok dengan akun yang terdaftar.']);
        }

        $user->forceFill([
            'google_id' => $google->getId(),
            'avatar_url' => $google->getAvatar(),
            'last_login_at' => now(),
        ])->save();

        return $this->signIn($request, $user);
    }

    /** Login tanpa Google khusus pengembangan lokal (DEV_LOGIN=true & APP_ENV=local). */
    public function devLogin(Request $request): RedirectResponse
    {
        abort_unless(config('capstone.dev_login') && app()->environment('local'), 404);
        $user = User::query()->where('is_active', true)->findOrFail($request->integer('user_id'));

        return $this->signIn($request, $user);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function signIn(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, remember: true);
        $request->session()->regenerate();
        ActivityLog::record('login');

        // F59 — kembali ke halaman yang dituju (mis. halaman penilaian dari QR).
        return redirect()->intended(route($user->homeRoute()));
    }
}
