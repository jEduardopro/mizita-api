<?php

use App\Http\Exceptions\RenderDomainFailure;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfOnboarded;
use App\Http\Middleware\RequireBusinessMembership;
use App\Http\Middleware\SetBusinessContext;
use App\Http\Middleware\SetLocale;
use App\Shared\Contracts\DomainFailure;
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
        $middleware->statefulApi();

        // SetLocale is prepended so the locale is resolved before anything
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

        $middleware->api(prepend: [
            SetLocale::class,
        ]);

        // A display preference, not a credential: the frontend reads it from
        // JavaScript, and SetLocale runs before cookies are decrypted. The name is
        // config('localization.cookie'), inlined because config is not loaded yet.
        $middleware->encryptCookies(except: [
            'locale',
        ]);

        // "business" resolves the tenant. The other two only decide whether the
        // caller is on the right page yet, and bind nothing.
        $middleware->alias([
            'business' => SetBusinessContext::class,
            'onboarded' => RequireBusinessMembership::class,
            'onboarding' => RedirectIfOnboarded::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Laravel matches a renderer by the first parameter's type, so typing
        // against the interface covers every domain failure there will ever be.
        // A new exception never comes back here.
        $exceptions->render(function (DomainFailure $failure, Request $request) {
            return app(RenderDomainFailure::class)($failure, $request);
        });

        $exceptions->shouldRenderJsonWhen(
            // An Inertia visit must never get a JSON error body: a
            // ValidationException has to stay a redirect back with errors, or every
            // auth form fails silently. The header check holds even if a client or
            // a test helper negotiates JSON while sending X-Inertia.
            fn (Request $request) => ! $request->hasHeader('X-Inertia')
                && ($request->is('api/*') || $request->expectsJson()),
        );
    })->create();
