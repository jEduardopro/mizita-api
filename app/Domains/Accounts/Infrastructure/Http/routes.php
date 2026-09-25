<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Http\Controllers\GoogleIdTokenController;
use App\Domains\Accounts\Infrastructure\Http\Controllers\SignInSecurityController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/google', GoogleIdTokenController::class)
    ->middleware('throttle:6,1');

Route::get('/me/security', SignInSecurityController::class)
    ->middleware(['auth:sanctum', 'throttle:'.SignInSecurityController::RATE_LIMITER]);
