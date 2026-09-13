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
    /**
     * Wire this domain's ports to their infrastructure adapters.
     */
    public function register(): void
    {
        $this->app->bind(CustomerRepository::class, EloquentCustomerRepository::class);
    }

    public function boot(): void
    {
        // The alias this domain answers to in polymorphic columns, matching
        // PhoneOwnerType::Customer. enforceMorphMap rather than morphMap, so an
        // unmapped model raises instead of quietly writing its FQCN into a
        // varchar(32) that would truncate it; it merges, so each domain keeps
        // declaring its own alias in its own provider.
        Relation::enforceMorphMap(['customer' => CustomerModel::class]);

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
