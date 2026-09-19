<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Http\Controllers\PublicAvailabilityController;
use App\Domains\PublicCatalog\Infrastructure\Http\Controllers\PublicBookingController;
use App\Domains\PublicCatalog\Infrastructure\Http\Controllers\PublicBusinessPageController;
use App\Domains\PublicCatalog\PublicCatalogServiceProvider;
use Illuminate\Support\Facades\Route;

Route::get('/public/businesses/{slug}', [PublicBusinessPageController::class, 'show'])
    ->where('slug', PublicCatalogServiceProvider::SLUG_PATTERN)
    ->name('public.businesses.show');

Route::get('/public/businesses/{slug}/availability', [PublicAvailabilityController::class, 'show'])
    ->where('slug', PublicCatalogServiceProvider::SLUG_PATTERN)
    ->name('public.businesses.availability');

Route::get('/public/businesses/{slug}/bookings/{reference}', [PublicBookingController::class, 'show'])
    ->where([
        'slug' => PublicCatalogServiceProvider::SLUG_PATTERN,
        'reference' => PublicCatalogServiceProvider::REFERENCE_CODE_PATTERN,
    ])
    ->name('public.businesses.bookings.show');
