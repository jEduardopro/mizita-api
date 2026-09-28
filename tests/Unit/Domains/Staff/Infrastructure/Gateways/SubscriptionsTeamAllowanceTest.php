<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Gateways\SubscriptionsTeamAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPeriod;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->subscriptions = new class implements SubscriptionRepository
    {
        /**
         * @var array<string, Subscription>
         */
        public array $inEffect = [];

        /**
         * @var list<list<string>>
         */
        public array $batchLookups = [];

        public function save(Subscription $subscription): void
        {
            $this->inEffect[$subscription->businessId] = $subscription;
        }

        public function inEffectFor(string $businessId, DateTimeImmutable $now): ?Subscription
        {
            return $this->inEffect[$businessId] ?? null;
        }

        public function inEffectForMany(array $businessIds, DateTimeImmutable $now): array
        {
            $this->batchLookups[] = $businessIds;

            return array_intersect_key($this->inEffect, array_flip($businessIds));
        }

        public function dueForExpiry(DateTimeImmutable $now): array
        {
            return [];
        }

        public function historyOf(string $businessId): array
        {
            return [];
        }
    };

    $this->subscribe = function (string $businessId): void {
        $this->subscriptions->save(Subscription::restore(
            id: '01930000-0000-7000-8000-0000000000f1',
            businessId: $businessId,
            plan: Plan::Complete,
            status: SubscriptionStatus::Active,
            period: SubscriptionPeriod::restore(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), null),
            price: Plan::Complete->listPrice(),
            createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        ));
    };

    $this->allowance = new SubscriptionsTeamAllowance(new BusinessPlans($this->subscriptions, new FakeClock));
});

it('includes the team for a business on the complete plan', function () {
    ($this->subscribe)(FakeBusinessContext::BUSINESS_ID);

    expect($this->allowance->includesTeam(FakeBusinessContext::BUSINESS_ID))->toBeTrue();
});

it('excludes the team for a business with no subscription in effect', function () {
    expect($this->allowance->includesTeam(FakeBusinessContext::BUSINESS_ID))->toBeFalse();
});

it('keeps only the businesses whose plan includes the team, in the order asked', function () {
    ($this->subscribe)(StaffFixtures::THIRD_BUSINESS_ID);
    ($this->subscribe)(FakeBusinessContext::BUSINESS_ID);

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

    expect($this->subscriptions->batchLookups)->toBe([[FakeBusinessContext::BUSINESS_ID, StaffFixtures::OTHER_BUSINESS_ID]]);
});

it('asks nothing for an empty batch', function () {
    expect($this->allowance->businessesIncludingTeam([]))->toBe([])
        ->and($this->subscriptions->batchLookups)->toBe([]);
});
