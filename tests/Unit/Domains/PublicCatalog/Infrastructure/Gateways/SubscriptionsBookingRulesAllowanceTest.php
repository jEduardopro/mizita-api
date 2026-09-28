<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Gateways\SubscriptionsBookingRulesAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;
use App\Domains\Subscriptions\ValueObjects\Plan;
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

    $this->subscribe = function (string $businessId, ?string $endsAt = SubscriptionFixtures::ENDS_AT): void {
        $this->subscriptions->store(SubscriptionFixtures::subscription(
            endsAt: $endsAt,
            businessId: $businessId,
            plan: Plan::Complete,
        ));
    };
});

it('includes booking rules for a business with a complete subscription in effect', function () {
    ($this->subscribe)(PublicCatalogFixtures::BUSINESS_ID);

    expect($this->allowance->includesBookingRules(PublicCatalogFixtures::BUSINESS_ID))->toBeTrue();
});

it('excludes booking rules for a business with no subscription at all', function () {
    expect($this->allowance->includesBookingRules(PublicCatalogFixtures::BUSINESS_ID))->toBeFalse();
});

it('excludes booking rules once the complete subscription has ended', function () {
    ($this->subscribe)(PublicCatalogFixtures::BUSINESS_ID, endsAt: '2026-06-10T00:00:00+00:00');

    expect($this->allowance->includesBookingRules(PublicCatalogFixtures::BUSINESS_ID))->toBeFalse();
});

it('never lends one business the plan another business subscribed to', function () {
    ($this->subscribe)(PublicCatalogFixtures::OTHER_BUSINESS_ID);

    expect($this->allowance->includesBookingRules(PublicCatalogFixtures::BUSINESS_ID))->toBeFalse();
});

it('asks for the subscription of that business uuid at the injected now', function () {
    $this->allowance->includesBookingRules(PublicCatalogFixtures::BUSINESS_ID);

    expect($this->subscriptions->inEffectLookups)->toEqual([
        ['businessId' => PublicCatalogFixtures::BUSINESS_ID, 'now' => SubscriptionFixtures::now()],
    ]);
});
