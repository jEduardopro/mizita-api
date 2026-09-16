<?php

declare(strict_types=1);

use App\Domains\Addresses\Infrastructure\Http\Controllers\StateController;
use Illuminate\Support\Facades\Route;

Route::get('/states', [StateController::class, 'index']);
