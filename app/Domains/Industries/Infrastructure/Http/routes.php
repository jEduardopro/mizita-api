<?php

declare(strict_types=1);

use App\Domains\Industries\Infrastructure\Http\Controllers\IndustryController;
use Illuminate\Support\Facades\Route;

Route::get('/industries', [IndustryController::class, 'index']);
