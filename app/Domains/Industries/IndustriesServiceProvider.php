<?php

declare(strict_types=1);

namespace App\Domains\Industries;

use App\Domains\Industries\Contracts\IndustryRepository;
use App\Domains\Industries\Infrastructure\Eloquent\EloquentIndustryRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IndustriesServiceProvider extends ServiceProvider
{
    /**
     * Wire this domain's ports to their infrastructure adapters.
     */
    public function register(): void
    {
        $this->app->bind(IndustryRepository::class, EloquentIndustryRepository::class);
    }

    /**
     * The catalog is a root domain, so no business context is bound: it is the
     * same list for every caller. Authentication still applies - only someone
     * setting up a business needs it - and the throttle keeps a public-shaped
     * read from being hammered.
     */
    public function boot(): void
    {
        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'throttle:60,1'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
