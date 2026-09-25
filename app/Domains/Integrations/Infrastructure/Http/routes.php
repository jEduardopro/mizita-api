<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Http\Controllers\GoogleCalendarAuthorizationController;
use App\Domains\Integrations\Infrastructure\Http\Controllers\GoogleCalendarConnectionController;
use App\Domains\Integrations\Infrastructure\Http\Controllers\IntegrationController;
use Illuminate\Support\Facades\Route;

Route::get('/integrations', [IntegrationController::class, 'index'])
    ->middleware('permission:manage_integrations');

Route::post('/integrations/google-calendar/authorizations', [GoogleCalendarAuthorizationController::class, 'store'])
    ->middleware('permission:manage_integrations');

Route::delete('/integrations/google-calendar/connection', [GoogleCalendarConnectionController::class, 'destroy'])
    ->middleware('permission:manage_integrations');
