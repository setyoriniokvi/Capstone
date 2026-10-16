<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** F56 — endpoint workflow automation hanya menerima token rahasia dari server. */
class VerifyAutomationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('capstone.automation.token');
        $given = (string) $request->header('X-Automation-Token');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Token automation tidak valid.'], 401);
        }

        return $next($request);
    }
}
