<?php

declare(strict_types=1);

use App\Domains\Availability\Infrastructure\Http\Controllers\MyScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/me/schedule', [MyScheduleController::class, 'show']);
Route::put('/me/schedule', [MyScheduleController::class, 'update']);
