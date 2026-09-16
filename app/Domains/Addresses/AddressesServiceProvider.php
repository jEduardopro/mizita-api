<?php

declare(strict_types=1);

namespace App\Domains\Addresses;

use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\Contracts\StateCatalog;
use App\Domains\Addresses\Infrastructure\Eloquent\EloquentAddressRepository;
use App\Domains\Addresses\Infrastructure\Eloquent\EloquentStateCatalog;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AddressesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AddressRepository::class, EloquentAddressRepository::class);
        $this->app->bind(StateCatalog::class, EloquentStateCatalog::class);
    }

    public function boot(): void
    {
        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'throttle:60,1'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
