<?php

use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\ForzarHttps;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', [
            CabecerasSeguridad::class,
            ForzarHttps::class,
            'throttle:web',
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('panel.resumen'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->booting(function (): void {
        RateLimiter::for('web', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    })
    ->create();
