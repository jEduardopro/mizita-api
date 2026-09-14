<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Http\Controllers\BusinessController;
use App\Domains\Businesses\Infrastructure\Http\Controllers\BusinessNameAvailabilityController;
use Illuminate\Support\Facades\Route;

Route::post('/businesses', [BusinessController::class, 'store'])
    ->middleware('throttle:20,1');

Route::get('/businesses/availability', BusinessNameAvailabilityController::class)
    ->middleware('throttle:60,1');
