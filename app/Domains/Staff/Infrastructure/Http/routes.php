<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Http\Controllers\MyProfileController;
use App\Domains\Staff\Infrastructure\Http\Controllers\MyProfilePhotoController;
use App\Domains\Staff\Infrastructure\Http\Controllers\StaffMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/staff', [StaffMemberController::class, 'index']);

Route::get('/me/profile', [MyProfileController::class, 'show']);

Route::patch('/me/profile', [MyProfileController::class, 'update']);

Route::delete('/me/profile/photo', [MyProfilePhotoController::class, 'destroy']);
