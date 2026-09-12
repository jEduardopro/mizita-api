<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Http\Controllers\GoogleCallbackController;
use App\Domains\Accounts\Infrastructure\Http\Controllers\GoogleRedirectController;
use Illuminate\Support\Facades\Route;

/*
| Browser routes owned by the Accounts domain. Loaded by AccountsServiceProvider
| on the "web" group, with no "api" prefix: an OAuth round trip is a navigation
| that carries its "state" value in the session, not an API call.
|
| Throttled because both are unauthenticated and reachable by anyone.
*/

Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('/auth/google/redirect', GoogleRedirectController::class)
        ->name('auth.google.redirect');

    Route::get('/auth/google/callback', GoogleCallbackController::class)
        ->name('auth.google.callback');
});
