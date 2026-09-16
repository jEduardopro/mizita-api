<?php

declare(strict_types=1);

use App\Domains\BookingPages\Infrastructure\Http\Controllers\BookingPageBannerController;
use App\Domains\BookingPages\Infrastructure\Http\Controllers\BookingPageGalleryController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:edit_business_settings')->group(function (): void {
    Route::post('/booking-page/banner', [BookingPageBannerController::class, 'store']);
    Route::delete('/booking-page/banner', [BookingPageBannerController::class, 'destroy']);

    Route::post('/booking-page/gallery', [BookingPageGalleryController::class, 'store']);
    Route::patch('/booking-page/gallery/order', [BookingPageGalleryController::class, 'reorder']);
    Route::delete('/booking-page/gallery/{image}', [BookingPageGalleryController::class, 'destroy'])
        ->whereUuid('image');
});
