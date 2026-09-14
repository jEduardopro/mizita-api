<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Http\Controllers\GoogleCallbackController;
use App\Domains\Accounts\Infrastructure\Http\Controllers\GoogleRedirectController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('/auth/google/redirect', GoogleRedirectController::class)
        ->name('auth.google.redirect');

    Route::get('/auth/google/callback', GoogleCallbackController::class)
        ->name('auth.google.callback');
});
