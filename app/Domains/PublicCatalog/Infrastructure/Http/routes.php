<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Http\Controllers\PublicBusinessPageController;
use App\Domains\PublicCatalog\PublicCatalogServiceProvider;
use Illuminate\Support\Facades\Route;

Route::get('/public/businesses/{slug}', [PublicBusinessPageController::class, 'show'])
    ->where('slug', PublicCatalogServiceProvider::SLUG_PATTERN)
    ->name('public.businesses.show');
