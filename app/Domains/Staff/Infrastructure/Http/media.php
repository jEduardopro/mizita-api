<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Http\Controllers\MyProfilePhotoController;
use Illuminate\Support\Facades\Route;

Route::post('/me/profile/photo', [MyProfilePhotoController::class, 'store']);
