<?php

use App\Http\Exceptions\RenderDomainFailure;
use App\Http\Logging\LogUnexpectedFailure;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfOnboarded;
use App\Http\Middleware\RequireBusinessMembership;
use App\Http\Middleware\SetBusinessContext;
use App\Http\Middleware\SetLocale;
use App\Http\Responses\JsonFailureRendering;
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

        $middleware->encryptCookies(except: [
            'locale',
        ]);

        $middleware->alias([
            'business' => SetBusinessContext::class,
            'onboarded' => RequireBusinessMembership::class,
            'onboarding' => RedirectIfOnboarded::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(DomainFailure::class);

        $exceptions->report(function (Throwable $error): bool {
            app(LogUnexpectedFailure::class)($error);

            return false;
        });

        $exceptions->render(function (DomainFailure $failure, Request $request) {
            return app(RenderDomainFailure::class)($failure, $request);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => JsonFailureRendering::appliesTo($request),
        );
    })->create();
