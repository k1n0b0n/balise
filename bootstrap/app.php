<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // In production, the host's nginx terminates HTTPS and the web container forwards its headers (ADR-0020);
        // PHP is only reachable through them.
        $middleware->trustProxies(at: '*');
        // Since every proxy is trusted, the host it forwards must be ours: links and asset URLs are built from it. The
        // exact host of APP_URL, no subdomain; without one, no host at all rather than any. Laravel applies it
        // outside local development and tests.
        $middleware->trustHosts(at: function (): array {
            $host = parse_url(Config::string('app.url'), PHP_URL_HOST);

            return [is_string($host) && $host !== '' ? '^'.preg_quote($host).'$' : '(?!)'];
        }, subdomains: false);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
