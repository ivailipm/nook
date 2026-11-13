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
        // In production Coolify's proxy terminates TLS and is the only way in: the container publishes no port,
        // and the proxy replaces any X-Forwarded-* headers a visitor sends. A list of private ranges didn't match
        // the address the proxy arrives from, so every URL came out as http:// and the browser blocked the CSS.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
