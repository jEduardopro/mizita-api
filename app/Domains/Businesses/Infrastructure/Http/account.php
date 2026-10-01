<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Http\Controllers\AccountBusinessesController;
use App\Domains\Businesses\Infrastructure\Http\Controllers\CurrentBusinessController;
use Illuminate\Support\Facades\Route;

Route::get('/me/businesses', AccountBusinessesController::class)
    ->middleware('throttle:60,1');

Route::put('/me/current-business', [CurrentBusinessController::class, 'update'])
    ->middleware('throttle:60,1');
