<?php

use App\Http\Middleware\EnsureUserOwnership;
use App\Http\Middleware\PreventCaching;
use App\Http\Middleware\SetNewsLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'prevent-caching' => PreventCaching::class,
            'user.ownership' => EnsureUserOwnership::class,
            'set-news-locale' => SetNewsLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
