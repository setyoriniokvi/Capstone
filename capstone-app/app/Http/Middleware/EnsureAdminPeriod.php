<?php

namespace App\Http\Middleware;

use App\Models\Period;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman admin selain pengelolaan periode berjalan di dalam "Dashboard Periode".
 * Bila admin belum membuka periode, diarahkan ke dashboard awal (Kelola Periode).
 */
class EnsureAdminPeriod
{
    /** Rute yang dikelola dari dashboard awal admin, tanpa periode terbuka. */
    private const OUTSIDE = ['admin.periods.*', 'admin.milestones.*', 'admin.workspace.*'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs(...self::OUTSIDE) && ! Period::workspace()) {
            session()->forget(Period::WORKSPACE_KEY);

            return redirect()->route('admin.periods.index')->with('status', 'Pilih periode terlebih dahulu untuk membuka dashboard periode.');
        }

        return $next($request);
    }
}
