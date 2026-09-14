<?php

declare(strict_types=1);

namespace App\Domains\Businesses;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\IndustryCatalog;
use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Contracts\RoleProvisioner;
use App\Domains\Businesses\Infrastructure\Eloquent\EloquentBusinessRepository;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Businesses\Infrastructure\Gateways\EloquentBusinessTeamKey;
use App\Domains\Businesses\Infrastructure\Gateways\IndustriesIndustryCatalog;
use App\Domains\Businesses\Infrastructure\Gateways\PhonesPhoneBook;
use App\Domains\Businesses\Infrastructure\Gateways\StaffOwnerRegistrar;
use App\Domains\Businesses\Infrastructure\Gateways\StaffRoleProvisioner;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class BusinessesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BusinessRepository::class, EloquentBusinessRepository::class);
        $this->app->bind(IndustryCatalog::class, IndustriesIndustryCatalog::class);
        $this->app->bind(OwnerRegistrar::class, StaffOwnerRegistrar::class);
        $this->app->bind(RoleProvisioner::class, StaffRoleProvisioner::class);
        $this->app->bind(PhoneBook::class, PhonesPhoneBook::class);
        $this->app->bind(BusinessTeamKey::class, EloquentBusinessTeamKey::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(['business' => BusinessModel::class]);

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum'])
            ->group(__DIR__.'/Infrastructure/Http/onboarding.php');

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
