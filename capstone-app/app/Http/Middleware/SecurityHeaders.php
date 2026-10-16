<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/** NFR keamanan — Content Security Policy dan header pengaman dasar. */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        $response = $next($request);

        $nonce = Vite::cspNonce();
        $dev = app()->environment('local') && file_exists(public_path('hot')) ? ' http://localhost:5173 ws://localhost:5173' : '';

        $response->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$dev}",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com{$dev}",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: https://*.googleusercontent.com",
            "connect-src 'self'{$dev}",
            "frame-ancestors 'self'",
            "form-action 'self' https://accounts.google.com",
            "base-uri 'self'",
        ]));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }
}
