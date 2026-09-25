<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Auth\PendingReactivation;
use App\Domains\Accounts\Infrastructure\Http\Controllers\AccountReactivationController;
use App\Domains\Accounts\Infrastructure\Http\Controllers\GoogleCallbackController;
use App\Domains\Accounts\Infrastructure\Http\Controllers\GoogleRedirectController;
use App\Shared\Contracts\Clock;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('/auth/google/redirect', GoogleRedirectController::class)
        ->name('auth.google.redirect');

    Route::get('/auth/google/callback', GoogleCallbackController::class)
        ->name('auth.google.callback');
});

Route::get('/account/reactivate', fn (PendingReactivation $pendingReactivation, Clock $clock) => $pendingReactivation->pendingAccountId($clock->now()) === null
    ? redirect()->route('login')
    : Inertia::render('auth/reactivate-account'))
    ->name(AccountReactivationController::REACTIVATE_ROUTE);
