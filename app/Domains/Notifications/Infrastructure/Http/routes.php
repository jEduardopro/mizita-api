<?php

declare(strict_types=1);

use App\Domains\Notifications\Infrastructure\Http\Controllers\StaffNotificationController;
use Illuminate\Support\Facades\Route;

Route::get('/notifications', [StaffNotificationController::class, 'index']);

Route::get('/notifications/unread-count', [StaffNotificationController::class, 'unreadCount']);

Route::get('/notifications/{notification}', [StaffNotificationController::class, 'show'])
    ->whereUuid('notification');

Route::post('/notifications/{notification}/read', [StaffNotificationController::class, 'markAsRead'])
    ->whereUuid('notification');
