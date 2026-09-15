<?php

declare(strict_types=1);

use App\Domains\Services\Infrastructure\Http\Controllers\ServiceController;
use App\Domains\Services\Infrastructure\Http\Controllers\ServiceImageController;
use Illuminate\Support\Facades\Route;

Route::get('/services', [ServiceController::class, 'index']);
Route::post('/services', [ServiceController::class, 'store']);
Route::get('/services/{service}', [ServiceController::class, 'show'])->whereUuid('service');
Route::match(['put', 'patch'], '/services/{service}', [ServiceController::class, 'update'])->whereUuid('service');
Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->whereUuid('service');
Route::post('/services/{service}/duplicate', [ServiceController::class, 'duplicate'])->whereUuid('service');
Route::delete('/services/{service}/image', [ServiceImageController::class, 'destroy'])->whereUuid('service');
