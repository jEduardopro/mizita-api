<?php

declare(strict_types=1);

use App\Domains\Platform\Infrastructure\Auth\PlatformGuard;
use App\Domains\Platform\Infrastructure\Http\Controllers\ImpersonationController;
use App\Domains\Platform\Infrastructure\Http\Controllers\PlatformLoginController;
use App\Domains\Platform\Infrastructure\Http\Controllers\PlatformLogoutController;
use App\Domains\Platform\Infrastructure\Http\Controllers\PlatformTwoFactorController;
use App\Domains\Platform\Infrastructure\Http\PlatformRoutes;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix(PlatformRoutes::PATH)->group(function (): void {
    Route::get('/', fn () => redirect()->route(PlatformRoutes::BUSINESSES))->name(PlatformRoutes::HOME);

    Route::middleware('guest:'.PlatformGuard::NAME)->group(function (): void {
        Route::get('/login', [PlatformLoginController::class, 'show'])->name(PlatformRoutes::LOGIN);

        Route::post('/login', [PlatformLoginController::class, 'store'])
            ->middleware('throttle:'.PlatformRoutes::LOGIN_LIMITER)
            ->name(PlatformRoutes::LOGIN_STORE);

        Route::get('/two-factor', [PlatformTwoFactorController::class, 'show'])->name(PlatformRoutes::TWO_FACTOR);

        Route::post('/two-factor', [PlatformTwoFactorController::class, 'store'])
            ->middleware('throttle:'.PlatformRoutes::TWO_FACTOR_LIMITER)
            ->name(PlatformRoutes::TWO_FACTOR_STORE);
    });

    Route::middleware('auth:'.PlatformGuard::NAME)->group(function (): void {
        Route::post('/logout', PlatformLogoutController::class)->name(PlatformRoutes::LOGOUT);

        Route::post('/impersonation/stop', [ImpersonationController::class, 'destroy'])
            ->name(PlatformRoutes::IMPERSONATION_STOP);

        Route::middleware(PlatformRoutes::SESSION_MIDDLEWARE)->group(function (): void {
            Route::get('/businesses', fn () => Inertia::render('platform/businesses/index'))
                ->name(PlatformRoutes::BUSINESSES);

            Route::post('/businesses/{business}/impersonation', [ImpersonationController::class, 'store'])
                ->whereUuid('business')
                ->name(PlatformRoutes::IMPERSONATION_START);
        });
    });
});
