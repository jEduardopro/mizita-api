<?php

namespace App\Providers;

use App\Http\Preferences\CookiePreferences;
use App\Models\User;
use App\Shared\Contracts\BusinessSelection;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\CurrentBusinessResolver;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\Contracts\TransactionManager;
use App\Shared\Infrastructure\EloquentTransactionManager;
use App\Shared\Infrastructure\LibPhoneNumberParser;
use App\Shared\Infrastructure\MembershipCurrentBusinessResolver;
use App\Shared\Infrastructure\SessionBusinessSelection;
use App\Shared\Infrastructure\SystemClock;
use App\Shared\Infrastructure\UuidGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public const API_RATE_LIMITER = 'api';

    private const API_REQUESTS_PER_MINUTE = 120;

    private const MINIMUM_PASSWORD_LENGTH = 8;

    public function register(): void
    {
        $this->app->bind(Clock::class, SystemClock::class);
        $this->app->bind(IdGenerator::class, UuidGenerator::class);
        $this->app->bind(TransactionManager::class, EloquentTransactionManager::class);
        $this->app->bind(BusinessSelection::class, SessionBusinessSelection::class);
        $this->app->bind(CurrentBusinessResolver::class, MembershipCurrentBusinessResolver::class);
        $this->app->singleton(PhoneNumberParser::class, LibPhoneNumberParser::class);
        $this->app->singleton(CookiePreferences::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(['user' => User::class]);

        Password::defaults(fn (): Password => Password::min(self::MINIMUM_PASSWORD_LENGTH)->mixedCase()->numbers()->symbols());

        $this->registerApiLimiter();
    }

    private function registerApiLimiter(): void
    {
        RateLimiter::for(
            self::API_RATE_LIMITER,
            static fn (Request $request): Limit => Limit::perMinute(self::API_REQUESTS_PER_MINUTE)
                ->by(self::apiLimiterKeyFor($request)),
        );
    }

    private static function apiLimiterKeyFor(Request $request): string
    {
        $accountId = $request->user()?->uuid;

        return $accountId === null ? 'ip:'.(string) $request->ip() : 'account:'.(string) $accountId;
    }
}
