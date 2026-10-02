<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Contracts\PaymentGraceEnforcementQueue;
use App\Domains\Subscriptions\Infrastructure\Queue\QueuedPaymentGraceEnforcement;
use App\Domains\Subscriptions\SubscriptionsServiceProvider;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

uses(TestCase::class);

const BILLING_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000000a1';

const OTHER_BILLING_ACCOUNT_UUID = '01930000-0000-7000-8000-0000000000a2';

function billingLimitFor(?User $caller, string $ip = '203.0.113.7'): Limit
{
    $request = Request::create('/api/subscription/checkout', 'POST', server: ['REMOTE_ADDR' => $ip]);
    $request->setUserResolver(fn () => $caller);

    return RateLimiter::limiter(SubscriptionsServiceProvider::BILLING_RATE_LIMITER)($request);
}

function billingAccount(string $uuid = BILLING_ACCOUNT_UUID): User
{
    return (new User)->forceFill(['id' => 7, 'uuid' => $uuid]);
}

/**
 * @return list<string>
 */
function subscriptionRouteMiddleware(string $method, string $uri): array
{
    $router = app(Router::class);
    $route = $router->getRoutes()->match(Request::create($uri, $method));

    return array_values(array_filter(
        $router->gatherRouteMiddleware($route),
        static fn (mixed $middleware): bool => is_string($middleware),
    ));
}

function throttledBy(string $limiter): string
{
    return ThrottleRequests::class.':'.$limiter;
}

it('binds the payment grace enforcement queue to the queued adapter', function () {
    expect($this->app->make(PaymentGraceEnforcementQueue::class))->toBeInstanceOf(QueuedPaymentGraceEnforcement::class);
});

describe('the billing limiter', function () {
    it('allows five billing writes a minute', function () {
        $limit = billingLimitFor(billingAccount());

        expect($limit->maxAttempts)->toBe(5)
            ->and($limit->decaySeconds)->toBe(60);
    });

    it('counts each account by its uuid', function () {
        expect(billingLimitFor(billingAccount())->key)->toBe('account:'.BILLING_ACCOUNT_UUID);
    });

    it('counts two accounts apart, even behind the same address', function () {
        expect(billingLimitFor(billingAccount())->key)
            ->not->toBe(billingLimitFor(billingAccount(OTHER_BILLING_ACCOUNT_UUID))->key);
    });

    it('falls back to the address when no account is signed in', function () {
        expect(billingLimitFor(null)->key)->toBe('ip:203.0.113.7');
    });
});

describe('the billing write routes', function () {
    it('throttles every billing write with the billing limiter', function (string $uri) {
        expect(subscriptionRouteMiddleware('POST', $uri))
            ->toContain(throttledBy(SubscriptionsServiceProvider::BILLING_RATE_LIMITER));
    })->with([
        'starting a checkout' => '/api/subscription/checkout',
        'confirming a checkout' => '/api/subscription/checkout/cs_test_0001/confirm',
        'switching to free' => '/api/subscription/switch-to-free',
        'resuming' => '/api/subscription/resume',
        'opening the billing portal' => '/api/subscription/billing-portal',
    ]);

    it('leaves the billing reads to the api limiter alone', function (string $uri) {
        expect(subscriptionRouteMiddleware('GET', $uri))
            ->not->toContain(throttledBy(SubscriptionsServiceProvider::BILLING_RATE_LIMITER))
            ->toContain(throttledBy(AppServiceProvider::API_RATE_LIMITER));
    })->with([
        'the subscription' => '/api/subscription',
        'the plans' => '/api/plans',
    ]);

    it('keeps the api limiter on the billing writes too', function () {
        expect(subscriptionRouteMiddleware('POST', '/api/subscription/checkout'))
            ->toContain(throttledBy(AppServiceProvider::API_RATE_LIMITER));
    });
});

describe('the stripe webhook route', function () {
    it('is throttled by the webhook limiter', function () {
        expect(subscriptionRouteMiddleware('POST', '/api/webhooks/stripe'))
            ->toContain(throttledBy(SubscriptionsServiceProvider::WEBHOOK_RATE_LIMITER));
    });

    it('is not counted against the api limiter, so a burst of Stripe events never locks out the dashboard', function () {
        expect(subscriptionRouteMiddleware('POST', '/api/webhooks/stripe'))
            ->not->toContain(throttledBy(AppServiceProvider::API_RATE_LIMITER));
    });

    it('carries no billing limiter, which would throttle Stripe at five events a minute', function () {
        expect(subscriptionRouteMiddleware('POST', '/api/webhooks/stripe'))
            ->not->toContain(throttledBy(SubscriptionsServiceProvider::BILLING_RATE_LIMITER));
    });
});
