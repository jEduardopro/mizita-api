<?php

declare(strict_types=1);

use App\Domains\Platform\Infrastructure\Http\Controllers\PlatformBusinessController;
use Illuminate\Support\Facades\Route;

Route::get('/platform/businesses', [PlatformBusinessController::class, 'index'])->name('platform.api.businesses.index');
