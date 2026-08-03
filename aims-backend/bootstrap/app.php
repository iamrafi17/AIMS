<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\RecordAuditLog;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CheckRole::class,
            'audit' => RecordAuditLog::class,
        ]);

        // AIMS authenticates API requests with Sanctum personal-access Bearer
        // tokens. Enabling stateful SPA middleware here would also require
        // session cookies and CSRF tokens, mixing two authentication modes.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
