<?php

declare(strict_types=1);

namespace App\Domains\Statistics;

use App\Domains\Statistics\Contracts\BusinessCurrency;
use App\Domains\Statistics\Contracts\BusinessTimezone;
use App\Domains\Statistics\Contracts\StatisticsReader;
use App\Domains\Statistics\Infrastructure\Gateways\BusinessesBusinessCurrency;
use App\Domains\Statistics\Infrastructure\Gateways\BusinessesBusinessTimezone;
use App\Domains\Statistics\Infrastructure\Gateways\CachedStatisticsReader;
use App\Domains\Statistics\Infrastructure\Gateways\EloquentStatisticsReader;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class StatisticsServiceProvider extends ServiceProvider
{
    public const RATE_LIMITER = 'statistics';

    private const API_MIDDLEWARE = ['api', 'auth:sanctum', 'business', 'owner.api', 'throttle:'.self::RATE_LIMITER];

    private const REQUESTS_PER_MINUTE = 30;

    private const CACHE_SECONDS = 60;

    public function register(): void
    {
        $this->app->bind(StatisticsReader::class, static fn (Application $app): StatisticsReader => new CachedStatisticsReader(
            $app->make(EloquentStatisticsReader::class),
            $app->make(Cache::class),
            self::CACHE_SECONDS,
        ));
        $this->app->bind(BusinessTimezone::class, BusinessesBusinessTimezone::class);
        $this->app->bind(BusinessCurrency::class, BusinessesBusinessCurrency::class);
    }

    public function boot(): void
    {
        $this->registerLimiter();

        Route::prefix('api')
            ->middleware(self::API_MIDDLEWARE)
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }

    private function registerLimiter(): void
    {
        RateLimiter::for(
            self::RATE_LIMITER,
            static fn (Request $request): Limit => Limit::perMinute(self::REQUESTS_PER_MINUTE)
                ->by(self::limiterKeyFor($request)),
        );
    }

    private static function limiterKeyFor(Request $request): string
    {
        $accountId = $request->user()?->uuid;

        return $accountId === null ? 'ip:'.(string) $request->ip() : 'account:'.(string) $accountId;
    }
}
