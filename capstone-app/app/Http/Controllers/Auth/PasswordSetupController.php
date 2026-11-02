<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * F21 — anggota tim mengatur password pertama lewat tautan di email keputusan tim. Tautan berupa Signed URL
 * (middleware "signed") tanpa kedaluwarsa dan hanya berlaku selama akun belum punya password.
 */
class PasswordSetupController extends Controller
{
    public function show(User $user): View|RedirectResponse
    {
        if (! $user->awaitingActivation()) {
            return $this->alreadySet();
        }

        return view('auth.set-password', [
            'user' => $user,
            'action' => URL::signedRoute('password.setup.store', ['user' => $user->id]),
            'signedInAs' => Auth::user()?->isNot($user) ? Auth::user() : null,
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        if (! $user->awaitingActivation()) {
            return $this->alreadySet();
        }
        $this->signOutOtherSession($request);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], ['password.confirmed' => 'Konfirmasi password tidak sama.']);

        $user->forceFill(['password' => $data['password']])->save();
        ActivityLog::create(['user_id' => $user->id, 'action' => 'set_password']);

        return redirect()->route('login')->with('status', 'Password berhasil diatur. Silakan masuk dengan email '.$user->email.'.');
    }

    /** Tautan dibuka di browser yang sedang masuk dengan akun lain (mis. admin): keluarkan sesi itu agar anggota bisa masuk. */
    private function signOutOtherSession(Request $request): void
    {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    private function alreadySet(): RedirectResponse
    {
        if (Auth::check()) {
            return redirect('/')->with('status', 'Password akun tersebut sudah diatur.');
        }

        return redirect()->route('login')->with('status', 'Password akun ini sudah diatur. Silakan masuk, atau hubungi admin bila lupa password.');
    }
}
