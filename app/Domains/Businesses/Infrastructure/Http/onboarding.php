<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Http\Controllers\BusinessController;
use App\Domains\Businesses\Infrastructure\Http\Controllers\BusinessNameAvailabilityController;
use Illuminate\Support\Facades\Route;

/*
| Onboarding: what a signed in account without a business can reach.
|
| Loaded by BusinessesServiceProvider under ['api', 'auth:sanctum'].
| Deliberately without the "business" middleware - see the provider for why
| adding it would lock every new owner out.
|
| The budget is per route rather than on the group, because the two have
| nothing in common: one is a write nobody performs twice, the other is a
| typeahead that fires on every pause in typing.
*/

// Registering the business. Twenty a minute is far more than a real owner
// needs and still narrow enough to make scripted signup unrewarding.
Route::post('/businesses', [BusinessController::class, 'store'])
    ->middleware('throttle:20,1');

// Name availability, read while the owner types. One request per settled
// pause means somebody deliberating over a name spends these legitimately,
// so a budget sized for the write would 429 a field they filled in correctly.
Route::get('/businesses/availability', BusinessNameAvailabilityController::class)
    ->middleware('throttle:60,1');
