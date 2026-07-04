<?php

use App\Http\Middleware\EnsureApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The container runs behind the host's nginx reverse proxy and is only
        // reachable through it (port published to 127.0.0.1, private Docker
        // network). nginx overwrites X-Forwarded-For with the real client IP
        // ($remote_addr, not append), so it can't be spoofed. Trust every proxy
        // so $request->ip() and HTTPS detection read nginx's X-Forwarded-* headers
        // instead of the Docker bridge gateway.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'api.key' => EnsureApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
