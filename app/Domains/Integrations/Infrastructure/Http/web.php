<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Http\Controllers\GoogleCalendarCallbackController;
use Illuminate\Support\Facades\Route;

Route::get('/integrations/google-calendar/callback', GoogleCalendarCallbackController::class)
    ->name(GoogleCalendarCallbackController::CALLBACK_ROUTE);
