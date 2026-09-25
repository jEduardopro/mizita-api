<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Http\Controllers\MyProfileController;
use App\Domains\Staff\Infrastructure\Http\Controllers\MyProfilePhotoController;
use App\Domains\Staff\Infrastructure\Http\Controllers\StaffMemberController;
use App\Domains\Staff\Infrastructure\Http\Controllers\TeamInvitationController;
use App\Domains\Staff\Infrastructure\Http\Controllers\TeamMemberController;
use App\Domains\Staff\Infrastructure\Http\Controllers\TeamMemberPhotoController;
use App\Domains\Staff\StaffServiceProvider;
use Illuminate\Support\Facades\Route;

Route::get('/staff', [StaffMemberController::class, 'index']);

Route::get('/me/profile', [MyProfileController::class, 'show']);

Route::patch('/me/profile', [MyProfileController::class, 'update']);

Route::delete('/me/profile/photo', [MyProfilePhotoController::class, 'destroy']);

Route::get('/staff-members', [TeamMemberController::class, 'index'])
    ->middleware('permission:view_staff_members');

Route::post('/staff-members', [TeamMemberController::class, 'store'])
    ->middleware(['permission:create_staff_member', 'throttle:'.StaffServiceProvider::INVITATION_LIMITER]);

Route::get('/staff-members/{staffMember}', [TeamMemberController::class, 'show'])
    ->middleware('permission:view_staff_members')
    ->whereUuid('staffMember');

Route::patch('/staff-members/{staffMember}', [TeamMemberController::class, 'update'])
    ->middleware('permission:edit_staff_member')
    ->whereUuid('staffMember');

Route::delete('/staff-members/{staffMember}', [TeamMemberController::class, 'destroy'])
    ->middleware('permission:delete_staff_member')
    ->whereUuid('staffMember');

Route::delete('/staff-members/{staffMember}/photo', [TeamMemberPhotoController::class, 'destroy'])
    ->middleware('permission:edit_staff_member')
    ->whereUuid('staffMember');

Route::post('/staff-members/{staffMember}/invitation', [TeamInvitationController::class, 'store'])
    ->middleware(['permission:create_staff_member', 'throttle:'.StaffServiceProvider::INVITATION_LIMITER])
    ->whereUuid('staffMember');
