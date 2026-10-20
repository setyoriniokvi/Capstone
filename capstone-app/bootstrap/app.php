<?php

use App\Http\Middleware\EnsureAdminPeriod;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyAutomationToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureRole::class,
            'admin.period' => EnsureAdminPeriod::class,
            'automation' => VerifyAutomationToken::class,
        ]);
        $middleware->web(append: [SecurityHeaders::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route(auth()->user()->homeRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
