<?php

namespace App\Providers;

use App\Models\User;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\Contracts\TransactionManager;
use App\Shared\Infrastructure\EloquentTransactionManager;
use App\Shared\Infrastructure\LibPhoneNumberParser;
use App\Shared\Infrastructure\SystemClock;
use App\Shared\Infrastructure\UuidGenerator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(IdGenerator::class, UuidGenerator::class);
        $this->app->bind(TransactionManager::class, EloquentTransactionManager::class);
        // A singleton, unlike its neighbours: the adapter wraps three of
        // libphonenumber's own singletons, each parsing a slab of numbering-plan
        // metadata on first touch.
        $this->app->singleton(PhoneNumberParser::class, LibPhoneNumberParser::class);
    }

    public function boot(): void
    {
        // Here rather than in a domain provider because User belongs to no
        // domain. Spatie writes model_has_roles.model_type from getMorphClass()
        // and Sanctum writes personal_access_tokens.tokenable_type the same way,
        // so with the morph map enforced an unmapped User would make both role
        // assignment and token creation throw.
        //
        // The string must never change once rows exist: every stored model_type
        // would stop matching its model.
        Relation::enforceMorphMap(['account' => User::class]);
    }
}
