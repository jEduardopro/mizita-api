<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Http\Controllers\MyProfilePhotoController;
use App\Domains\Staff\Infrastructure\Http\Controllers\TeamMemberPhotoController;
use Illuminate\Support\Facades\Route;

Route::post('/me/profile/photo', [MyProfilePhotoController::class, 'store']);

Route::post('/staff-members/{staffMember}/photo', [TeamMemberPhotoController::class, 'store'])
    ->middleware('permission:edit_staff_member')
    ->whereUuid('staffMember');
