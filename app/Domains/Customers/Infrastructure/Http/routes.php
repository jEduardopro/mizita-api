<?php

declare(strict_types=1);

use App\Domains\Customers\Infrastructure\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

/*
| Routes owned by the Customers domain. Loaded by CustomersServiceProvider
| under the "api" prefix and middleware group.
*/

Route::post('/customers', [CustomerController::class, 'store']);
