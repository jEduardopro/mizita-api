<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Http\Controllers\BusinessController;
use App\Domains\Businesses\Infrastructure\Http\Controllers\BusinessNameAvailabilityController;
use Illuminate\Support\Facades\Route;

/*
| Loaded by BusinessesServiceProvider under ['api', 'auth:sanctum'], deliberately
| without the "business" middleware - see the provider for why adding it would
| lock every new owner out.
|
| The throttle is per route rather than on the group, because a write nobody
| performs twice and a typeahead firing on every pause need different budgets.
*/

Route::post('/businesses', [BusinessController::class, 'store'])
    ->middleware('throttle:20,1');

Route::get('/businesses/availability', BusinessNameAvailabilityController::class)
    ->middleware('throttle:60,1');
