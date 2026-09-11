<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetBusinessContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lets same-origin requests from the Blade views authenticate against the
        // API using the session cookie, while native clients keep using bearer tokens.
        $middleware->statefulApi();

        // Inertia renders every web page, and preloaded assets get their Link headers.
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Applied by each tenant-scoped domain's route group.
        $middleware->alias([
            'business' => SetBusinessContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            // An Inertia visit must never get a JSON error body: a ValidationException
            // has to stay a redirect back with errors, or every auth form fails silently.
            // The Inertia client sends "Accept: text/html, application/xhtml+xml", so
            // expectsJson() is already false for it; the header check keeps that true
            // even if a client (or a test helper) negotiates JSON while sending X-Inertia.
            fn (Request $request) => ! $request->hasHeader('X-Inertia')
                && ($request->is('api/*') || $request->expectsJson()),
        );
    })->create();
