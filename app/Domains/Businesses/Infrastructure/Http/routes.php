<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Http\Controllers\BusinessSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/business/settings', [BusinessSettingsController::class, 'show'])
    ->middleware('permission:view_business_settings');

Route::patch('/business/settings', [BusinessSettingsController::class, 'update'])
    ->middleware('permission:edit_business_settings');
