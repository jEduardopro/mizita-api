<?php

namespace App\Providers;

use App\Models\User;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;
use App\Shared\Infrastructure\EloquentTransactionManager;
use App\Shared\Infrastructure\SystemClock;
use App\Shared\Infrastructure\UuidGenerator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(IdGenerator::class, UuidGenerator::class);
        $this->app->bind(TransactionManager::class, EloquentTransactionManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The alias App\Models\User answers to in polymorphic columns. It sits
        // here rather than in a domain provider because the model belongs to
        // no domain: it is the platform's authentication record.
        //
        // It is load-bearing in two places. Spatie writes
        // model_has_roles.model_type from getMorphClass(), and Sanctum writes
        // personal_access_tokens.tokenable_type the same way, so with the morph
        // map enforced - as the domain providers do - an unmapped User would
        // make both role assignment and token creation throw.
        //
        // enforceMorphMap merges across providers, so each domain still
        // declares its own alias. The string must never change once rows exist:
        // every stored model_type would stop matching its model.
        Relation::enforceMorphMap(['account' => User::class]);
    }
}
