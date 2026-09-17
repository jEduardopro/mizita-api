<?php

declare(strict_types=1);

use App\Domains\Customers\Infrastructure\Http\Controllers\CustomerController;
use App\Domains\Customers\Infrastructure\Http\Controllers\CustomerPhotoController;
use Illuminate\Support\Facades\Route;

Route::get('/customers', [CustomerController::class, 'index'])
    ->middleware('permission:view_customers');

Route::post('/customers', [CustomerController::class, 'store'])
    ->middleware('permission:create_customer');

Route::get('/customers/{customer}', [CustomerController::class, 'show'])
    ->middleware('permission:view_customers')
    ->whereUuid('customer');

Route::match(['put', 'patch'], '/customers/{customer}', [CustomerController::class, 'update'])
    ->middleware('permission:edit_customer')
    ->whereUuid('customer');

Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
    ->middleware('permission:delete_customer')
    ->whereUuid('customer');

Route::delete('/customers/{customer}/photo', [CustomerPhotoController::class, 'destroy'])
    ->middleware('permission:edit_customer')
    ->whereUuid('customer');
