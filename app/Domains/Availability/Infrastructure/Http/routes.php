<?php

declare(strict_types=1);

use App\Domains\Availability\Infrastructure\Http\Controllers\MyScheduleController;
use App\Domains\Availability\Infrastructure\Http\Controllers\StaffMemberScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/me/schedule', [MyScheduleController::class, 'show']);
Route::put('/me/schedule', [MyScheduleController::class, 'update']);

Route::get('/staff-members/{staffMember}/schedule', [StaffMemberScheduleController::class, 'show'])
    ->middleware('permission:view_staff_members')
    ->whereUuid('staffMember');

Route::put('/staff-members/{staffMember}/schedule', [StaffMemberScheduleController::class, 'update'])
    ->middleware('permission:edit_staff_member')
    ->whereUuid('staffMember');
