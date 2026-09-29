<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Infrastructure\Stripe\StripeApi;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeApiFailure;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeBillingSubscriptions;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeSnapshotMapper;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Stripe\ApiRequestor;
use Tests\Support\Subscriptions\FakeStripeHttpClient;
use Tests\Support\Subscriptions\StripePayloads;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const BILLING_SUBSCRIPTION_PATH = '/v1/subscriptions/'.SubscriptionFixtures::BILLING_SUBSCRIPTION_ID;

beforeEach(function () {
    $this->http = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($this->http);

    $this->billing = new StripeBillingSubscriptions(new StripeApi('sk_test_fake', 0), new StripeSnapshotMapper);
});

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

it('fetches a subscription and maps it', function () {
    $this->http->answering('GET', BILLING_SUBSCRIPTION_PATH, StripePayloads::subscription(['status' => 'past_due']));

    $snapshot = $this->billing->fetch(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);

    expect($snapshot->subscriptionId)->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID)
        ->and($snapshot->status)->toBe(SubscriptionStatus::PastDue)
        ->and($this->http->requestedRoutes())->toBe(['GET '.BILLING_SUBSCRIPTION_PATH]);
});

it('schedules the cancellation at the end of the period and maps what comes back', function () {
    $this->http->answering('POST', BILLING_SUBSCRIPTION_PATH, StripePayloads::subscription(['cancel_at_period_end' => true]));

    $snapshot = $this->billing->cancelAtPeriodEnd(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);

    expect($snapshot->cancelAtPeriodEnd)->toBeTrue()
        ->and($this->http->requests[0]['params'])->toBe(['cancel_at_period_end' => 'true']);
});

it('resumes by clearing the pending cancellation and maps what comes back', function () {
    $this->http->answering('POST', BILLING_SUBSCRIPTION_PATH, StripePayloads::subscription());

    $snapshot = $this->billing->resume(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);

    expect($snapshot->cancelAtPeriodEnd)->toBeFalse()
        ->and($this->http->requests[0]['params'])->toBe(['cancel_at_period_end' => 'false']);
});

describe('canceling right away', function () {
    it('cancels a subscription that is still running', function () {
        $this->http
            ->answering('GET', BILLING_SUBSCRIPTION_PATH, StripePayloads::subscription(['status' => 'past_due']))
            ->answering('DELETE', BILLING_SUBSCRIPTION_PATH, StripePayloads::subscription(['status' => 'canceled']));

        $snapshot = $this->billing->cancelNow(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);

        expect($snapshot->status)->toBe(SubscriptionStatus::Canceled)
            ->and($this->http->requestedRoutes())->toBe(['GET '.BILLING_SUBSCRIPTION_PATH, 'DELETE '.BILLING_SUBSCRIPTION_PATH]);
    });

    it('does not cancel again a subscription that is already canceled', function () {
        $this->http->answering('GET', BILLING_SUBSCRIPTION_PATH, StripePayloads::subscription(['status' => 'canceled']));

        $snapshot = $this->billing->cancelNow(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);

        expect($snapshot->status)->toBe(SubscriptionStatus::Canceled)
            ->and($this->http->requestedRoutes())->toBe(['GET '.BILLING_SUBSCRIPTION_PATH]);
    });
});

it('wraps a refusal of the billing provider in an infrastructure failure', function () {
    $this->http->missing('GET', BILLING_SUBSCRIPTION_PATH);

    expect(fn () => $this->billing->fetch(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID))
        ->toThrow(StripeApiFailure::class);
});
