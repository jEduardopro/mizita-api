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

it('describes the free plan of a business with nothing granting access', function () {
    expect($this->plan->describe(SubscriptionFixtures::BUSINESS_ID))->toBe([
        'name' => 'free',
        'entitlements' => [
            'team' => false,
            'max_active_services' => 3,
            'booking_rules' => false,
            'calendar_sync' => false,
        ],
    ]);
});

it('describes the complete plan of a business whose subscription grants access', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription());

    expect($this->plan->describe(SubscriptionFixtures::BUSINESS_ID))->toBe([
        'name' => 'complete',
        'entitlements' => [
            'team' => true,
            'max_active_services' => null,
            'booking_rules' => true,
            'calendar_sync' => true,
        ],
    ]);
});

it('describes only the business it was asked about', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID));

    expect($this->plan->describe(SubscriptionFixtures::BUSINESS_ID)['name'])->toBe('free');
});
