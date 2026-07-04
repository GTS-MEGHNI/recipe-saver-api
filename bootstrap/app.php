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
        // The container runs behind the host's nginx reverse proxy. Trust the
        // proxy's peer address (the Docker bridge gateway) so $request->ip()
        // reads the real client from nginx's X-Forwarded-For header instead of
        // the gateway. Set via TRUSTED_PROXIES (comma-separated, or `*`); config()
        // isn't available this early, so read env directly. If unset, no proxy is
        // trusted — fail safe, not open.
        $trustedProxies = (string) env('TRUSTED_PROXIES', '');
        $middleware->trustProxies(at: $trustedProxies === '*'
            ? '*'
            : array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))),
        );

        $middleware->alias([
            'api.key' => EnsureApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
