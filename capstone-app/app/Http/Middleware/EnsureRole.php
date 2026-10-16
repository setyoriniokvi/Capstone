<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** RBAC — membatasi rute berdasarkan peran pengguna. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki akses untuk fitur ini.');
        }

        if (! $user->is_active) {
            auth()->logout();
            abort(403, 'Akun Anda dinonaktifkan. Hubungi admin Capstone.');
        }

        return $next($request);
    }
}
