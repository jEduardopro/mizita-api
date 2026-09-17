<?php

declare(strict_types=1);

use App\Domains\Customers\Infrastructure\Http\Controllers\CustomerPhotoController;
use Illuminate\Support\Facades\Route;

Route::post('/customers/{customer}/photo', [CustomerPhotoController::class, 'store'])
    ->middleware('permission:edit_customer')
    ->whereUuid('customer');
