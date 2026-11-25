<?php

namespace App\Http\Controllers\Lecturer;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Dosen mengganti password sendiri (menu profil → Ganti Password). Akun hasil impor memakai password default
 * (config capstone.lecturer_default_password) sampai diganti di sini.
 */
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('dosen.password', ['usesDefault' => self::usesDefault($request->user())]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed', 'different:current_password'],
        ], [
            'current_password.required' => 'Isi password saat ini.',
            'current_password.current_password' => 'Password saat ini salah.',
            'password.required' => 'Isi password baru.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak sama.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ]);
        if ($data['password'] === config('capstone.lecturer_default_password')) {
            return back()->withErrors(['password' => 'Jangan memakai password default. Pilih password lain.']);
        }

        $user->forceFill(['password' => $data['password']])->save();
        // Perangkat lain yang masih masuk dengan password lama dikeluarkan; perangkat ini tetap masuk.
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        ActivityLog::record('change_password');

        return redirect()->route('dosen.password.edit')->with('status', 'Password berhasil diganti.');
    }

    /** Akun masih memakai password default hasil impor. */
    public static function usesDefault($user): bool
    {
        return $user->password && Hash::check((string) config('capstone.lecturer_default_password'), $user->password);
    }
}
