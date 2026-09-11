<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetBusinessContext;
use App\Http\Middleware\SetLocale;
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
        // SetLocale is prepended so the locale is already resolved before anything
        // downstream can produce a translated string - a validation error above all.
        $middleware->web(
            append: [
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
            prepend: [
                SetLocale::class,
            ],
        );

        // The API answers in the caller's language too: the browser negotiates it
        // through Accept-Language, a native client states it with X-Locale.
        $middleware->api(prepend: [
            SetLocale::class,
        ]);

        // The locale cookie is a plain preference, not a credential, and the
        // frontend reads it from JavaScript to boot i18next. It also has to stay
        // readable by SetLocale, which runs before cookies are decrypted. The name
        // is config('localization.cookie'), inlined because config is not loaded yet.
        $middleware->encryptCookies(except: [
            'locale',
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
