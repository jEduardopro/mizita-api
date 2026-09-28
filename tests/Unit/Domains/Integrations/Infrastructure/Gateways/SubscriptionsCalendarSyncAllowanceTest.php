<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Gateways\SubscriptionsCalendarSyncAllowance;
use App\Domains\Subscriptions\Application\Services\BusinessPlans;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPeriod;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;

const CALENDAR_SYNC_ALLOWANCE_NOW = '2026-09-25T10:00:00+00:00';

beforeEach(function () {
    $this->lookups = [];
    $this->inEffect = null;

    $subscriptions = Mockery::mock(SubscriptionRepository::class);
    $subscriptions->shouldReceive('inEffectFor')->andReturnUsing(function (string $businessId, DateTimeImmutable $now): ?Subscription {
        $this->lookups[] = ['businessId' => $businessId, 'now' => $now];

        return $this->inEffect;
    });

    $this->allowance = new SubscriptionsCalendarSyncAllowance(new BusinessPlans(
        $subscriptions,
        new FakeClock(new DateTimeImmutable(CALENDAR_SYNC_ALLOWANCE_NOW)),
    ));
});

it('includes calendar sync for a business with a complete subscription in effect', function () {
    $this->inEffect = Subscription::restore(
        id: '01930000-0000-7000-8000-000000000301',
        businessId: FakeBusinessContext::BUSINESS_ID,
        plan: Plan::Complete,
        status: SubscriptionStatus::Active,
        period: SubscriptionPeriod::restore(new DateTimeImmutable('2026-09-01T00:00:00+00:00'), null),
        price: Plan::Complete->listPrice(),
        createdAt: new DateTimeImmutable('2026-09-01T00:00:00+00:00'),
    );

    expect($this->allowance->includesCalendarSync(FakeBusinessContext::BUSINESS_ID))->toBeTrue();
});

it('excludes calendar sync for a business with no subscription in effect', function () {
    expect($this->allowance->includesCalendarSync(FakeBusinessContext::BUSINESS_ID))->toBeFalse();
});

it('asks for the subscription of that business uuid at the injected now', function () {
    $this->allowance->includesCalendarSync(FakeBusinessContext::BUSINESS_ID);

    expect($this->lookups)->toHaveCount(1)
        ->and($this->lookups[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($this->lookups[0]['now'])->toEqual(new DateTimeImmutable(CALENDAR_SYNC_ALLOWANCE_NOW));
});
