<?php

declare(strict_types=1);

use App\Domains\Appointments\Infrastructure\Gateways\SubscriptionsBookingPreferencesAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;

    $this->allowance = new SubscriptionsBookingPreferencesAllowance(new BusinessPlans(
        $this->subscriptions,
        new FakeClock(SubscriptionFixtures::now()),
    ));
});

it('includes booking preferences for a business with a complete subscription granting access', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(businessId: FakeBusinessContext::BUSINESS_ID));

    expect($this->allowance->includesBookingPreferences(FakeBusinessContext::BUSINESS_ID))->toBeTrue();
});

it('excludes booking preferences for a business whose checkout never completed', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(
        status: SubscriptionStatus::Incomplete,
        currentPeriodEndsAt: null,
        startedAt: null,
        billingSubscriptionId: null,
        businessId: FakeBusinessContext::BUSINESS_ID,
    ));

    expect($this->allowance->includesBookingPreferences(FakeBusinessContext::BUSINESS_ID))->toBeFalse();
});

it('excludes booking preferences for a business with no subscription at all', function () {
    expect($this->allowance->includesBookingPreferences(FakeBusinessContext::BUSINESS_ID))->toBeFalse();
});

it('asks for the subscription of that business uuid', function () {
    $this->allowance->includesBookingPreferences(FakeBusinessContext::BUSINESS_ID);

    expect($this->subscriptions->businessLookups)->toBe([FakeBusinessContext::BUSINESS_ID]);
});
