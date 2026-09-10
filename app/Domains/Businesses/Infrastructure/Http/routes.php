<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Http\Controllers\BusinessController;
use Illuminate\Support\Facades\Route;

/*
| Routes owned by the Businesses domain. Loaded by BusinessesServiceProvider
| under the "api" prefix and middleware group.
*/

Route::post('/businesses', [BusinessController::class, 'store']);
