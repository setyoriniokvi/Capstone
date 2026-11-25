<?php

namespace App\Http\Middleware;

use App\Support\PeriodContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setelah sesi tersedia: tentukan periode konteks request ini (lihat PeriodContext) dan muat bentuk penilaian &
 * rubrik peer review periode tersebut ke config. Saat aplikasi boot sesi belum ada, sehingga config awal
 * mengikuti periode aktif.
 */
class SyncPeriodContext
{
    public function handle(Request $request, Closure $next): Response
    {
        PeriodContext::beginRequest();

        return $next($request);
    }
}
