<?php

declare(strict_types=1);

namespace App\Domains\Businesses;

use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\IndustryCatalog;
use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Infrastructure\Eloquent\EloquentBusinessRepository;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Businesses\Infrastructure\Gateways\EloquentBusinessTeamKey;
use App\Domains\Businesses\Infrastructure\Gateways\IndustriesIndustryCatalog;
use App\Domains\Businesses\Infrastructure\Gateways\PhonesPhoneBook;
use App\Domains\Businesses\Infrastructure\Gateways\StaffOwnerRegistrar;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class BusinessesServiceProvider extends ServiceProvider
{
    /**
     * Wire this domain's ports to their infrastructure adapters.
     *
     * Three of the first four are gateways onto other domains. That indirection
     * is the whole boundary: it is what keeps Industries, Staff and Phones out
     * of every import list above Infrastructure, and what lets OnboardBusiness
     * be built from mocks alone.
     *
     * BusinessTeamKey is a shared port rather than one of this domain's,
     * because its consumer is the HTTP edge and must not import Businesses.
     * It is bound here because this domain owns the table it reads.
     */
    public function register(): void
    {
        $this->app->bind(BusinessRepository::class, EloquentBusinessRepository::class);
        $this->app->bind(IndustryCatalog::class, IndustriesIndustryCatalog::class);
        $this->app->bind(OwnerRegistrar::class, StaffOwnerRegistrar::class);
        $this->app->bind(PhoneBook::class, PhonesPhoneBook::class);
        $this->app->bind(BusinessTeamKey::class, EloquentBusinessTeamKey::class);
    }

    public function boot(): void
    {
        // A business is referenced polymorphically - by a phone number today,
        // by more later - so the alias is what stops the fully qualified class
        // name being written into rows that outlive any refactor.
        //
        // enforceMorphMap, not morphMap: an unmapped model then fails loudly
        // instead of quietly storing its class name. The calls merge, so each
        // domain declares only its own.
        Relation::enforceMorphMap(['business' => BusinessModel::class]);

        // Onboarding runs without the "business" middleware on purpose. These
        // are the routes that produce the membership that middleware demands,
        // so requiring one here would mean nobody could ever create their
        // first business. Adding the alias "for consistency" closes signup.
        //
        // No throttle on the group: each route declares its own, because a
        // budget that suits the write starves the typeahead beside it.
        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum'])
            ->group(__DIR__.'/Infrastructure/Http/onboarding.php');

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
