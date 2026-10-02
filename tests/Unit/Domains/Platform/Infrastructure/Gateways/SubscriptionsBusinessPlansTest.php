<?php

declare(strict_types=1);

use App\Domains\Platform\Infrastructure\Gateways\SubscriptionsBusinessPlans;
use App\Domains\Platform\ValueObjects\Plan;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\Plan as SubscriptionPlan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->plans = new SubscriptionsBusinessPlans($this->subscriptions);
});

it('shows a business with no subscription on the free plan', function () {
    expect($this->plans->plansOf([SubscriptionFixtures::BUSINESS_ID], SubscriptionFixtures::now()))
        ->toBe([SubscriptionFixtures::BUSINESS_ID => Plan::Free]);
});

it('shows a business on the plan its subscription grants at that instant', function (Subscription $subscription, Plan $expected) {
    $this->subscriptions->store($subscription);

    expect($this->plans->plansOf([SubscriptionFixtures::BUSINESS_ID], SubscriptionFixtures::now()))
        ->toBe([SubscriptionFixtures::BUSINESS_ID => $expected]);
})->with([
    'active within its paid period' => [
        SubscriptionFixtures::subscription(),
        Plan::Complete,
    ],
    'trialing within its period' => [
        SubscriptionFixtures::subscription(status: SubscriptionStatus::Trialing),
        Plan::Complete,
    ],
    'canceled at period end, period not over yet' => [
        SubscriptionFixtures::subscription(canceledAt: '2026-06-10T10:00:00+00:00'),
        Plan::Complete,
    ],
    'past due within the payment grace' => [
        SubscriptionFixtures::subscription(status: SubscriptionStatus::PastDue, paymentFailedAt: '2026-06-12T15:00:00+00:00'),
        Plan::Complete,
    ],
    'active with its period expired past the renewal leeway' => [
        SubscriptionFixtures::subscription(currentPeriodEndsAt: '2026-06-13T15:00:00+00:00'),
        Plan::Free,
    ],
    'canceled for good' => [
        SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled, canceledAt: '2026-06-10T10:00:00+00:00'),
        Plan::Free,
    ],
    'past due beyond the payment grace' => [
        SubscriptionFixtures::subscription(status: SubscriptionStatus::PastDue, paymentFailedAt: '2026-06-01T15:00:00+00:00'),
        Plan::Free,
    ],
    'checkout never completed' => [
        SubscriptionFixtures::subscription(status: SubscriptionStatus::Incomplete, currentPeriodEndsAt: null, startedAt: null),
        Plan::Free,
    ],
    'active on the free plan' => [
        SubscriptionFixtures::subscription(plan: SubscriptionPlan::Free),
        Plan::Free,
    ],
]);

it('judges the subscription at the instant it is given, not at any other', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription());

    $beforeTheEnd = $this->plans->plansOf([SubscriptionFixtures::BUSINESS_ID], new DateTimeImmutable('2026-07-02T14:59:59+00:00'));
    $afterTheEnd = $this->plans->plansOf([SubscriptionFixtures::BUSINESS_ID], new DateTimeImmutable('2026-07-02T15:00:00+00:00'));

    expect($beforeTheEnd[SubscriptionFixtures::BUSINESS_ID])->toBe(Plan::Complete)
        ->and($afterTheEnd[SubscriptionFixtures::BUSINESS_ID])->toBe(Plan::Free);
});

it('answers a plan for every business it was asked about, in the order asked', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID));

    expect($this->plans->plansOf(
        [SubscriptionFixtures::OTHER_BUSINESS_ID, SubscriptionFixtures::BUSINESS_ID],
        SubscriptionFixtures::now(),
    ))->toBe([
        SubscriptionFixtures::OTHER_BUSINESS_ID => Plan::Complete,
        SubscriptionFixtures::BUSINESS_ID => Plan::Free,
    ]);
});

it('reads every subscription of the page in one batch, never one business at a time', function () {
    $this->plans->plansOf(
        [SubscriptionFixtures::BUSINESS_ID, SubscriptionFixtures::OTHER_BUSINESS_ID],
        SubscriptionFixtures::now(),
    );

    expect($this->subscriptions->manyBusinessesLookups)->toBe([
        [SubscriptionFixtures::BUSINESS_ID, SubscriptionFixtures::OTHER_BUSINESS_ID],
    ])->and($this->subscriptions->businessLookups)->toBe([]);
});

it('answers no plan when asked about no business', function () {
    expect($this->plans->plansOf([], SubscriptionFixtures::now()))->toBe([]);
});
