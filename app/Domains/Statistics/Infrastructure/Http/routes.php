<?php

declare(strict_types=1);

use App\Domains\Statistics\Infrastructure\Http\Controllers\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::get('/statistics', [StatisticsController::class, 'show']);
