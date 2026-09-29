<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\UseCases\ReconcileLapsedSubscriptions;
use App\Shared\Application\UseCaseResponse;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\FakeSubscriptionSyncQueue;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const LAPSED_PERIOD_ENDS_AT = '2026-06-13T15:00:00+00:00';

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->syncQueue = new FakeSubscriptionSyncQueue;

    $this->reconcile = fn (): UseCaseResponse => (new ReconcileLapsedSubscriptions(
        $this->subscriptions,
        $this->syncQueue,
        new FakeClock(SubscriptionFixtures::now()),
    ))->handle();
});

it('schedules a billing sync for every lapsed subscription and reports how many', function () {
    $this->subscriptions->store(
        SubscriptionFixtures::subscription(currentPeriodEndsAt: LAPSED_PERIOD_ENDS_AT),
        SubscriptionFixtures::subscription(
            currentPeriodEndsAt: LAPSED_PERIOD_ENDS_AT,
            billingSubscriptionId: SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID,
            id: SubscriptionFixtures::OTHER_SUBSCRIPTION_ID,
            businessId: SubscriptionFixtures::OTHER_BUSINESS_ID,
            billingCustomerId: SubscriptionFixtures::OTHER_BILLING_CUSTOMER_ID,
        ),
    );

    expect(($this->reconcile)()->value())->toBe(2)
        ->and($this->syncQueue->scheduled)->toBe([
            SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
            SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID,
        ]);
});

it('skips a lapsed row with no billing subscription to fetch', function () {
    $this->subscriptions->reportingLapsed(
        SubscriptionFixtures::subscription(currentPeriodEndsAt: LAPSED_PERIOD_ENDS_AT, billingSubscriptionId: null),
    );

    expect(($this->reconcile)()->value())->toBe(0)
        ->and($this->syncQueue->scheduled)->toBe([]);
});

it('asks the repository for the lapsed rows at the instant of the clock', function () {
    ($this->reconcile)();

    expect($this->subscriptions->lapsedLookups)->toEqual([SubscriptionFixtures::now()]);
});

it('schedules nothing when no subscription lapsed', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription());

    expect(($this->reconcile)()->value())->toBe(0)
        ->and($this->syncQueue->scheduled)->toBe([]);
});
