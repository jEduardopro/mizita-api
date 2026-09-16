<?php

declare(strict_types=1);

use App\Domains\Businesses\Infrastructure\Http\Controllers\BusinessLogoController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:edit_business_settings')->group(function (): void {
    Route::post('/business/logo', [BusinessLogoController::class, 'store']);
    Route::delete('/business/logo', [BusinessLogoController::class, 'destroy']);
});
