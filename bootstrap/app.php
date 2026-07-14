<?php

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
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordChanged::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperadmin::class,
            'custom.app' => \App\Http\Middleware\EnsureCustomAppAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
