<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnforcePairedDeviceSession;
use App\Http\Middleware\EnsureVisitorIsHuman;
use App\Http\Middleware\NotModifiedWhenUnchanged;
use App\Services\ShowStream;
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
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'human' => EnsureVisitorIsHuman::class,
            'not-modified' => NotModifiedWhenUnchanged::class,
        ]);

        // Read by the Mercure hub, which cannot decrypt it.
        $middleware->encryptCookies(except: [ShowStream::COOKIE]);

        // Appended, so it runs inside StartSession and can still reach the cookie
        // that session is about to be handed.
        $middleware->appendToGroup('web', EnforcePairedDeviceSession::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
