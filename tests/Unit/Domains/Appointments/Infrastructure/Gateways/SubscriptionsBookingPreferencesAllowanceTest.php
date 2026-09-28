<?php

declare(strict_types=1);

use App\Domains\Appointments\Infrastructure\Gateways\SubscriptionsBookingPreferencesAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\Plan;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const BOOKING_PREFERENCES_ALLOWANCE_NOW = '2026-09-25T10:00:00+00:00';

beforeEach(function () {
    $this->lookups = [];
    $this->inEffect = null;

    $subscriptions = Mockery::mock(SubscriptionRepository::class);
    $subscriptions->shouldReceive('inEffectFor')->andReturnUsing(function (string $businessId, DateTimeImmutable $now): ?Subscription {
        $this->lookups[] = ['businessId' => $businessId, 'now' => $now];

        return $this->inEffect;
    });

    $this->allowance = new SubscriptionsBookingPreferencesAllowance(new BusinessPlans(
        $subscriptions,
        new FakeClock(new DateTimeImmutable(BOOKING_PREFERENCES_ALLOWANCE_NOW)),
    ));
});

it('includes booking preferences for a business with a complete subscription in effect', function () {
    $this->inEffect = SubscriptionFixtures::subscription(businessId: FakeBusinessContext::BUSINESS_ID, plan: Plan::Complete);

    expect($this->allowance->includesBookingPreferences(FakeBusinessContext::BUSINESS_ID))->toBeTrue();
});

it('excludes booking preferences for a business with a free subscription in effect', function () {
    $this->inEffect = SubscriptionFixtures::subscription(businessId: FakeBusinessContext::BUSINESS_ID, plan: Plan::Free, amount: 0);

    expect($this->allowance->includesBookingPreferences(FakeBusinessContext::BUSINESS_ID))->toBeFalse();
});

it('excludes booking preferences for a business with no subscription in effect', function () {
    expect($this->allowance->includesBookingPreferences(FakeBusinessContext::BUSINESS_ID))->toBeFalse();
});

it('asks for the subscription of that business uuid at the injected now', function () {
    $this->allowance->includesBookingPreferences(FakeBusinessContext::BUSINESS_ID);

    expect($this->lookups)->toHaveCount(1)
        ->and($this->lookups[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($this->lookups[0]['now'])->toEqual(new DateTimeImmutable(BOOKING_PREFERENCES_ALLOWANCE_NOW));
});
