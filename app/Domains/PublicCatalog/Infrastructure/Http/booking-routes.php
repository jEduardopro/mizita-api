<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Http\Controllers\PublicBookingController;
use App\Domains\PublicCatalog\PublicCatalogServiceProvider;
use Illuminate\Support\Facades\Route;

Route::post('/public/businesses/{slug}/bookings', [PublicBookingController::class, 'store'])
    ->where('slug', PublicCatalogServiceProvider::SLUG_PATTERN)
    ->name('public.businesses.bookings.store');

Route::patch('/public/businesses/{slug}/bookings/{reference}', [PublicBookingController::class, 'update'])
    ->where([
        'slug' => PublicCatalogServiceProvider::SLUG_PATTERN,
        'reference' => PublicCatalogServiceProvider::REFERENCE_CODE_PATTERN,
    ])
    ->name('public.businesses.bookings.update');

Route::delete('/public/businesses/{slug}/bookings/{reference}', [PublicBookingController::class, 'destroy'])
    ->where([
        'slug' => PublicCatalogServiceProvider::SLUG_PATTERN,
        'reference' => PublicCatalogServiceProvider::REFERENCE_CODE_PATTERN,
    ])
    ->name('public.businesses.bookings.destroy');
