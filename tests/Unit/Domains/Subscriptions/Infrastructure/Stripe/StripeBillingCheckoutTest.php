<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Exceptions\CheckoutSessionNotFound;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeApi;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeApiFailure;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeBillingCheckout;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeSnapshotMapper;
use App\Domains\Subscriptions\ValueObjects\CheckoutRequest;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Stripe\ApiRequestor;
use Tests\Support\Subscriptions\FakeStripeHttpClient;
use Tests\Support\Subscriptions\StripePayloads;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const STRIPE_CHECKOUT_RETURN_URL = 'https://mizita.test/settings/plan?session_id={CHECKOUT_SESSION_ID}';

const STRIPE_SESSION_PATH = '/v1/checkout/sessions/'.StripePayloads::SESSION_ID;

const STRIPE_SUBSCRIPTION_PATH = '/v1/subscriptions/'.SubscriptionFixtures::BILLING_SUBSCRIPTION_ID;

function checkoutRequest(?int $trialDays = null): CheckoutRequest
{
    return new CheckoutRequest(
        businessId: SubscriptionFixtures::BUSINESS_ID,
        billingCustomerId: SubscriptionFixtures::BILLING_CUSTOMER_ID,
        billingPriceId: SubscriptionFixtures::BILLING_PRICE_ID,
        trialDays: $trialDays,
        returnUrl: STRIPE_CHECKOUT_RETURN_URL,
    );
}

beforeEach(function () {
    $this->http = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($this->http);

    $this->checkout = new StripeBillingCheckout(new StripeApi('sk_test_fake', 0), new StripeSnapshotMapper);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

describe('starting a checkout', function () {
    beforeEach(function () {
        $this->http->answering('POST', '/v1/checkout/sessions', StripePayloads::checkoutSession(['subscription' => null]));
    });

    it('hands back the session id and its client secret', function () {
        $session = $this->checkout->start(checkoutRequest());

        expect($session->id)->toBe(StripePayloads::SESSION_ID)
            ->and($session->clientSecret)->toBe(StripePayloads::CLIENT_SECRET);
    });

    it('opens an embedded subscription checkout of one seat of the plan price for the business customer', function () {
        $this->checkout->start(checkoutRequest());

        $params = $this->http->requests[0]['params'];

        expect($this->http->requestedRoutes())->toBe(['POST /v1/checkout/sessions'])
            ->and($params['mode'])->toBe('subscription')
            ->and($params['ui_mode'])->toBe('elements')
            ->and($params['customer'])->toBe(SubscriptionFixtures::BILLING_CUSTOMER_ID)
            ->and($params['line_items'])->toBe([['price' => SubscriptionFixtures::BILLING_PRICE_ID, 'quantity' => 1]])
            ->and($params['return_url'])->toBe(STRIPE_CHECKOUT_RETURN_URL);
    });

    it('tags the session and the subscription it will create with the business uuid', function () {
        $this->checkout->start(checkoutRequest());

        $params = $this->http->requests[0]['params'];

        expect($params['metadata'])->toBe(['business_id' => SubscriptionFixtures::BUSINESS_ID])
            ->and($params['subscription_data']['metadata'])->toBe(['business_id' => SubscriptionFixtures::BUSINESS_ID]);
    });

    it('asks for no trial when the request carries none', function () {
        $this->checkout->start(checkoutRequest());

        expect($this->http->requests[0]['params']['subscription_data'])->not->toHaveKey('trial_period_days');
    });

    it('asks for the trial the request carries', function () {
        $this->checkout->start(checkoutRequest(SubscriptionFixtures::TRIAL_DAYS));

        expect($this->http->requests[0]['params']['subscription_data']['trial_period_days'])->toBe(SubscriptionFixtures::TRIAL_DAYS);
    });
});

describe('reading the subscription of a session', function () {
    it('fetches the subscription the session points at and maps it', function () {
        $this->http
            ->answering('GET', STRIPE_SESSION_PATH, StripePayloads::checkoutSession())
            ->answering('GET', STRIPE_SUBSCRIPTION_PATH, StripePayloads::subscription());

        $snapshot = $this->checkout->subscriptionOf(StripePayloads::SESSION_ID, SubscriptionFixtures::BILLING_CUSTOMER_ID);

        expect($snapshot?->subscriptionId)->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID)
            ->and($snapshot?->status)->toBe(SubscriptionStatus::Active)
            ->and($this->http->requestedRoutes())->toBe(['GET '.STRIPE_SESSION_PATH, 'GET '.STRIPE_SUBSCRIPTION_PATH]);
    });

    it('maps an expanded subscription without fetching it again', function () {
        $this->http->answering('GET', STRIPE_SESSION_PATH, StripePayloads::checkoutSession([
            'subscription' => StripePayloads::subscription(),
        ]));

        $snapshot = $this->checkout->subscriptionOf(StripePayloads::SESSION_ID, SubscriptionFixtures::BILLING_CUSTOMER_ID);

        expect($snapshot?->subscriptionId)->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID)
            ->and($this->http->requestedRoutes())->toBe(['GET '.STRIPE_SESSION_PATH]);
    });

    it('accepts a session whose customer arrives expanded', function () {
        $this->http
            ->answering('GET', STRIPE_SESSION_PATH, StripePayloads::checkoutSession([
                'customer' => ['id' => SubscriptionFixtures::BILLING_CUSTOMER_ID, 'object' => 'customer'],
            ]))
            ->answering('GET', STRIPE_SUBSCRIPTION_PATH, StripePayloads::subscription());

        expect($this->checkout->subscriptionOf(StripePayloads::SESSION_ID, SubscriptionFixtures::BILLING_CUSTOMER_ID))
            ->not->toBeNull();
    });

    it('reads no subscription for a session that has not created one yet', function () {
        $this->http->answering('GET', STRIPE_SESSION_PATH, StripePayloads::checkoutSession(['subscription' => null]));

        expect($this->checkout->subscriptionOf(StripePayloads::SESSION_ID, SubscriptionFixtures::BILLING_CUSTOMER_ID))->toBeNull();
    });

    it('treats a session of another customer as not found, fetching nothing more', function () {
        $this->http->answering('GET', STRIPE_SESSION_PATH, StripePayloads::checkoutSession([
            'customer' => SubscriptionFixtures::OTHER_BILLING_CUSTOMER_ID,
        ]));

        expect(fn () => $this->checkout->subscriptionOf(StripePayloads::SESSION_ID, SubscriptionFixtures::BILLING_CUSTOMER_ID))
            ->toThrow(CheckoutSessionNotFound::class)
            ->and($this->http->requestedRoutes())->toBe(['GET '.STRIPE_SESSION_PATH]);
    });

    it('treats a session with no customer as not found', function () {
        $this->http->answering('GET', STRIPE_SESSION_PATH, StripePayloads::checkoutSession(['customer' => null]));

        expect(fn () => $this->checkout->subscriptionOf(StripePayloads::SESSION_ID, SubscriptionFixtures::BILLING_CUSTOMER_ID))
            ->toThrow(CheckoutSessionNotFound::class);
    });

    it('treats a session the billing provider does not know as not found', function () {
        $this->http->missing('GET', STRIPE_SESSION_PATH);

        expect(fn () => $this->checkout->subscriptionOf(StripePayloads::SESSION_ID, SubscriptionFixtures::BILLING_CUSTOMER_ID))
            ->toThrow(CheckoutSessionNotFound::class);
    });

    it('lets any other refusal through as an infrastructure failure', function () {
        $this->http->answering('GET', STRIPE_SESSION_PATH, [
            'error' => ['type' => 'api_error', 'message' => 'Something went wrong'],
        ], 500);

        expect(fn () => $this->checkout->subscriptionOf(StripePayloads::SESSION_ID, SubscriptionFixtures::BILLING_CUSTOMER_ID))
            ->toThrow(StripeApiFailure::class);
    });
});
