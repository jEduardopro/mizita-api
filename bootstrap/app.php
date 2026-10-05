<?php

use App\Domains\Platform\Infrastructure\Http\Middleware\EnforceImpersonation;
use App\Domains\Platform\Infrastructure\Http\Middleware\RequirePlatformSession;
use App\Domains\Platform\Infrastructure\Http\PlatformRoutes;
use App\Http\Exceptions\RenderDomainFailure;
use App\Http\Exceptions\RenderNotFoundPage;
use App\Http\Logging\LogUnexpectedFailure;
use App\Http\Middleware\ForbidNonOwners;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\HandlePreferences;
use App\Http\Middleware\RedirectIfOnboarded;
use App\Http\Middleware\RequireBusinessMembership;
use App\Http\Middleware\RequireBusinessOwner;
use App\Http\Middleware\RequireFreshPassword;
use App\Http\Middleware\RequirePermission;
use App\Http\Middleware\RestrictSearchIndexing;
use App\Http\Middleware\SetBusinessContext;
use App\Http\Middleware\SetLocale;
use App\Http\Responses\JsonFailureRendering;
use App\Providers\AppServiceProvider;
use App\Shared\Contracts\DomainFailure;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->throttleApi(AppServiceProvider::API_RATE_LIMITER);

        $middleware->web(
            append: [
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
                EnforceImpersonation::class,
                RestrictSearchIndexing::class,
            ],
            prepend: [
                SetLocale::class,
                HandlePreferences::class,
            ],
        );

        $middleware->api(
            append: [
                EnforceImpersonation::class,
            ],
            prepend: [
                SetLocale::class,
            ],
        );

        $middleware->encryptCookies(except: [
            'locale',
            'appearance',
            'sidebar_state',
        ]);

        $middleware->redirectGuestsTo(
            fn (Request $request): string => PlatformRoutes::owns($request)
                ? route(PlatformRoutes::LOGIN)
                : route('login'),
        );

        $middleware->redirectUsersTo(
            fn (Request $request): string => PlatformRoutes::owns($request)
                ? route(PlatformRoutes::BUSINESSES)
                : route(RequireBusinessMembership::ONBOARDING_ROUTE),
        );

        $middleware->group('business', [
            RequireFreshPassword::class,
            SetBusinessContext::class,
        ]);

        $middleware->alias([
            'onboarded' => RequireBusinessMembership::class,
            'onboarding' => RedirectIfOnboarded::class,
            'owner' => RequireBusinessOwner::class,
            'owner.api' => ForbidNonOwners::class,
            'permission' => RequirePermission::class,
            PlatformRoutes::SESSION_MIDDLEWARE => RequirePlatformSession::class,
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

        $exceptions->render(function (NotFoundHttpException $missing, Request $request) {
            return app(RenderNotFoundPage::class)($request);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => JsonFailureRendering::appliesTo($request),
        );
    })->create();
