<?php

declare(strict_types=1);

namespace App\Domains\Industries;

use App\Domains\Industries\Contracts\IndustryRepository;
use App\Domains\Industries\Infrastructure\Eloquent\EloquentIndustryRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IndustriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IndustryRepository::class, EloquentIndustryRepository::class);
    }

    public function boot(): void
    {
        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'throttle:60,1'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
