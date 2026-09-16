<?php

declare(strict_types=1);

use App\Domains\Services\Infrastructure\Http\Controllers\ServiceController;
use App\Domains\Services\Infrastructure\Http\Controllers\ServiceImageController;
use Illuminate\Support\Facades\Route;

Route::get('/services', [ServiceController::class, 'index'])
    ->middleware('permission:view_services');

Route::post('/services', [ServiceController::class, 'store'])
    ->middleware('permission:create_service');

Route::get('/services/{service}', [ServiceController::class, 'show'])
    ->middleware('permission:view_services')
    ->whereUuid('service');

Route::match(['put', 'patch'], '/services/{service}', [ServiceController::class, 'update'])
    ->middleware('permission:edit_service')
    ->whereUuid('service');

Route::delete('/services/{service}', [ServiceController::class, 'destroy'])
    ->middleware('permission:delete_service')
    ->whereUuid('service');

Route::post('/services/{service}/duplicate', [ServiceController::class, 'duplicate'])
    ->middleware('permission:create_service')
    ->whereUuid('service');

Route::delete('/services/{service}/image', [ServiceImageController::class, 'destroy'])
    ->middleware('permission:edit_service')
    ->whereUuid('service');
