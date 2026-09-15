<?php

namespace App\Providers;

use App\Http\Preferences\CookiePreferences;
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
        $this->app->singleton(PhoneNumberParser::class, LibPhoneNumberParser::class);
        $this->app->singleton(CookiePreferences::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(['user' => User::class]);
    }
}
