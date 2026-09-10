<?php

declare(strict_types=1);

namespace App\Domains\Businesses;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Infrastructure\Eloquent\EloquentBusinessRepository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class BusinessesServiceProvider extends ServiceProvider
{
    /**
     * Wire this domain's ports to their infrastructure adapters.
     */
    public function register(): void
    {
        $this->app->bind(BusinessRepository::class, EloquentBusinessRepository::class);
    }

    public function boot(): void
    {
        Route::prefix('api')
            ->middleware(['api'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
