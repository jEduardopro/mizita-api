<?php

declare(strict_types=1);

use App\Domains\Statistics\Contracts\StatisticsReader;
use App\Domains\Statistics\Infrastructure\Gateways\CachedStatisticsReader;
use App\Domains\Statistics\StatisticsServiceProvider;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

uses(TestCase::class);

const STATISTICS_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000000a1';

const OTHER_STATISTICS_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000000a2';

function statisticsLimitFor(?string $accountUuid): Limit
{
    $request = Request::create('/api/statistics', server: ['REMOTE_ADDR' => '203.0.113.7']);
    $request->setUserResolver(fn () => $accountUuid === null ? null : (new User)->forceFill(['id' => 7, 'uuid' => $accountUuid]));

    return RateLimiter::limiter(StatisticsServiceProvider::RATE_LIMITER)($request);
}

/**
 * @return list<string>
 */
function statisticsRouteMiddleware(): array
{
    $router = app(Router::class);

    return array_values(array_filter(
        $router->gatherRouteMiddleware($router->getRoutes()->match(Request::create('/api/statistics'))),
        static fn (mixed $middleware): bool => is_string($middleware),
    ));
}

it('reads statistics through the cache', function () {
    expect($this->app->make(StatisticsReader::class))->toBeInstanceOf(CachedStatisticsReader::class);
});

describe('the statistics limiter', function () {
    it('allows thirty reports a minute', function () {
        $limit = statisticsLimitFor(STATISTICS_ACCOUNT_UUID);

        expect($limit->maxAttempts)->toBe(30)
            ->and($limit->decaySeconds)->toBe(60);
    });

    it('counts each account by its uuid', function () {
        expect(statisticsLimitFor(STATISTICS_ACCOUNT_UUID)->key)->toBe('account:'.STATISTICS_ACCOUNT_UUID);
    });

    it('counts two accounts apart', function () {
        expect(statisticsLimitFor(STATISTICS_ACCOUNT_UUID)->key)->not->toBe(statisticsLimitFor(OTHER_STATISTICS_ACCOUNT_UUID)->key);
    });

    it('falls back to the address when no account is signed in', function () {
        expect(statisticsLimitFor(null)->key)->toBe('ip:203.0.113.7');
    });
});

describe('the statistics route', function () {
    it('is throttled by the statistics limiter', function () {
        expect(statisticsRouteMiddleware())->toContain(ThrottleRequests::class.':'.StatisticsServiceProvider::RATE_LIMITER);
    });

    it('is throttled by the api limiter as well', function () {
        expect(statisticsRouteMiddleware())->toContain(ThrottleRequests::class.':'.AppServiceProvider::API_RATE_LIMITER);
    });
});
