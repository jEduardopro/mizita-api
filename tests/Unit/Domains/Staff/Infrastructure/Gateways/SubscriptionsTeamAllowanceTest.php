<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Gateways\SubscriptionsTeamAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\Staff\StaffFixtures;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;

    $this->subscribe = function (string $businessId, string $id): void {
        $this->subscriptions->store(SubscriptionFixtures::subscription(id: $id, businessId: $businessId));
    };

    $this->allowance = new SubscriptionsTeamAllowance(new BusinessPlans(
        $this->subscriptions,
        new FakeClock(SubscriptionFixtures::now()),
    ));
});

it('includes the team for a business on the complete plan', function () {
    ($this->subscribe)(FakeBusinessContext::BUSINESS_ID, SubscriptionFixtures::SUBSCRIPTION_ID);

    expect($this->allowance->includesTeam(FakeBusinessContext::BUSINESS_ID))->toBeTrue();
});

it('excludes the team for a business with no subscription granting access', function () {
    expect($this->allowance->includesTeam(FakeBusinessContext::BUSINESS_ID))->toBeFalse();
});

it('keeps only the businesses whose plan includes the team, in the order asked', function () {
    ($this->subscribe)(StaffFixtures::THIRD_BUSINESS_ID, SubscriptionFixtures::SUBSCRIPTION_ID);
    ($this->subscribe)(FakeBusinessContext::BUSINESS_ID, SubscriptionFixtures::OTHER_SUBSCRIPTION_ID);

    expect($this->allowance->businessesIncludingTeam([
        StaffFixtures::THIRD_BUSINESS_ID,
        StaffFixtures::OTHER_BUSINESS_ID,
        FakeBusinessContext::BUSINESS_ID,
    ]))->toBe([StaffFixtures::THIRD_BUSINESS_ID, FakeBusinessContext::BUSINESS_ID]);
});

it('reads the plans once for the whole batch, asking about each business only once', function () {
    $this->allowance->businessesIncludingTeam([
        FakeBusinessContext::BUSINESS_ID,
        StaffFixtures::OTHER_BUSINESS_ID,
        FakeBusinessContext::BUSINESS_ID,
    ]);

    expect($this->subscriptions->manyBusinessesLookups)->toBe([[FakeBusinessContext::BUSINESS_ID, StaffFixtures::OTHER_BUSINESS_ID]]);
});

it('asks nothing for an empty batch', function () {
    expect($this->allowance->businessesIncludingTeam([]))->toBe([])
        ->and($this->subscriptions->manyBusinessesLookups)->toBe([]);
});
