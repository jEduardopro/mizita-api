<?php

declare(strict_types=1);

use App\Domains\Appointments\Infrastructure\Http\Controllers\AppointmentController;
use App\Domains\Appointments\Infrastructure\Http\Controllers\CustomerAppointmentController;
use Illuminate\Support\Facades\Route;

Route::get('/appointments', [AppointmentController::class, 'index'])
    ->middleware('permission:view_appointments');

Route::post('/appointments', [AppointmentController::class, 'store'])
    ->middleware('permission:create_appointment');

Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])
    ->middleware('permission:view_appointments')
    ->whereUuid('appointment');

Route::match(['put', 'patch'], '/appointments/{appointment}', [AppointmentController::class, 'update'])
    ->middleware('permission:edit_appointment')
    ->whereUuid('appointment');

Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])
    ->middleware('permission:delete_appointment')
    ->whereUuid('appointment');

Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])
    ->middleware('permission:delete_appointment')
    ->whereUuid('appointment');

Route::get('/customers/{customer}/appointments', [CustomerAppointmentController::class, 'index'])
    ->middleware('permission:view_appointments')
    ->whereUuid('customer');
