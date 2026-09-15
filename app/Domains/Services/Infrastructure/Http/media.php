<?php

declare(strict_types=1);

use App\Domains\Services\Infrastructure\Http\Controllers\ServiceImageController;
use Illuminate\Support\Facades\Route;

Route::post('/services/{service}/image', [ServiceImageController::class, 'store'])->whereUuid('service');
