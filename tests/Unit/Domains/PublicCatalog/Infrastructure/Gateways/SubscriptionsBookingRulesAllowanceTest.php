<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Gateways\SubscriptionsBookingRulesAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;
use Tests\Support\FakeClock;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;

    $this->allowance = new SubscriptionsBookingRulesAllowance(new BusinessPlans(
        $this->subscriptions,
        new FakeClock(SubscriptionFixtures::now()),
    ));

    $this->subscribe = function (string $businessId, string $currentPeriodEndsAt = SubscriptionFixtures::PERIOD_ENDS_AT): void {
        $this->subscriptions->store(SubscriptionFixtures::subscription(
            currentPeriodEndsAt: $currentPeriodEndsAt,
            businessId: $businessId,
        ));
    };
});

it('includes booking rules for a business with a complete subscription granting access', function () {
    ($this->subscribe)(PublicCatalogFixtures::BUSINESS_ID);

    expect($this->allowance->includesBookingRules(PublicCatalogFixtures::BUSINESS_ID))->toBeTrue();
});

it('excludes booking rules for a business with no subscription at all', function () {
    ($this->subscribe)(PublicCatalogFixtures::OTHER_BUSINESS_ID);

    expect($this->allowance->includesBookingRules(PublicCatalogFixtures::BUSINESS_ID))->toBeFalse();
});

it('excludes booking rules once the paid period and its renewal leeway have run out', function () {
    ($this->subscribe)(PublicCatalogFixtures::BUSINESS_ID, currentPeriodEndsAt: '2026-06-14T15:00:00+00:00');

    expect($this->allowance->includesBookingRules(PublicCatalogFixtures::BUSINESS_ID))->toBeFalse();
});

it('asks for the subscription of that business uuid', function () {
    $this->allowance->includesBookingRules(PublicCatalogFixtures::BUSINESS_ID);

    expect($this->subscriptions->businessLookups)->toBe([PublicCatalogFixtures::BUSINESS_ID]);
});
