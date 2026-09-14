<?php

declare(strict_types=1);

namespace App\Domains\Customers;

use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Infrastructure\Eloquent\EloquentCustomerRepository;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CustomersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerRepository::class, EloquentCustomerRepository::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(['customer' => CustomerModel::class]);

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
