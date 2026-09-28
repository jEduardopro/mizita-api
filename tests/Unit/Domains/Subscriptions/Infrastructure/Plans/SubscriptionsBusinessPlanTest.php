<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\UseCases\ShowBusinessPlan;
use App\Domains\Subscriptions\Infrastructure\Plans\SubscriptionsBusinessPlan;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;

    $this->plan = new SubscriptionsBusinessPlan(
        new ShowBusinessPlan($this->subscriptions, new FakeClock(SubscriptionFixtures::now())),
    );
});

it('describes the free plan of a business with nothing in effect', function () {
    expect($this->plan->describe(SubscriptionFixtures::BUSINESS_ID))->toBe([
        'name' => 'free',
        'ends_at' => null,
        'entitlements' => [
            'team' => false,
            'max_active_services' => 3,
            'booking_rules' => false,
            'calendar_sync' => false,
        ],
    ]);
});

it('describes the complete plan with its end as an ATOM instant in UTC', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription());

    expect($this->plan->describe(SubscriptionFixtures::BUSINESS_ID))->toBe([
        'name' => 'complete',
        'ends_at' => '2026-07-01T06:00:00+00:00',
        'entitlements' => [
            'team' => true,
            'max_active_services' => null,
            'booking_rules' => true,
            'calendar_sync' => true,
        ],
    ]);
});

it('describes an open-ended complete plan with no end', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(endsAt: null));

    expect($this->plan->describe(SubscriptionFixtures::BUSINESS_ID)['ends_at'])->toBeNull();
});

it('describes only the business it was asked about', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID));

    expect($this->plan->describe(SubscriptionFixtures::BUSINESS_ID)['name'])->toBe('free');
});
