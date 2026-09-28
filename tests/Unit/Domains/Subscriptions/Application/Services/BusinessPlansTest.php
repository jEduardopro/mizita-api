<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Services\BusinessPlans;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanEntitlements;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const BUSINESS_PLANS_THIRD_BUSINESS_ID = '01930000-0000-7000-8000-00000000b003';

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->clock = new FakeClock(SubscriptionFixtures::now());
    $this->plans = new BusinessPlans($this->subscriptions, $this->clock);
});

describe('one business', function () {
    it('puts a business that never subscribed on the free plan', function () {
        expect($this->plans->planOf(SubscriptionFixtures::BUSINESS_ID))->toBe(Plan::Free)
            ->and($this->plans->entitlementsOf(SubscriptionFixtures::BUSINESS_ID))->toEqual(PlanEntitlements::free());
    });

    it('puts a business with a subscription in effect on the plan it grants', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());

        expect($this->plans->planOf(SubscriptionFixtures::BUSINESS_ID))->toBe(Plan::Complete)
            ->and($this->plans->entitlementsOf(SubscriptionFixtures::BUSINESS_ID))->toEqual(PlanEntitlements::complete());
    });

    it('resolves the plan from the clock, so it falls to free when the period ends with no expiry run', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());

        $this->clock->advance('P15DT14H59M59S');
        $lastSecond = $this->plans->planOf(SubscriptionFixtures::BUSINESS_ID);

        $this->clock->advance('PT1S');
        $atTheEnd = $this->plans->planOf(SubscriptionFixtures::BUSINESS_ID);

        expect($lastSecond)->toBe(Plan::Complete)
            ->and($atTheEnd)->toBe(Plan::Free);
    });

    it('puts a business on free before its subscription starts', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(startsAt: '2026-07-01T06:00:00+00:00', endsAt: '2026-08-01T06:00:00+00:00'));

        expect($this->plans->planOf(SubscriptionFixtures::BUSINESS_ID))->toBe(Plan::Free);
    });

    it('puts a business whose subscription was canceled on free', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled));

        expect($this->plans->planOf(SubscriptionFixtures::BUSINESS_ID))->toBe(Plan::Free);
    });

    it('never lends one business the subscription of another', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID));

        expect($this->plans->planOf(SubscriptionFixtures::BUSINESS_ID))->toBe(Plan::Free);
    });

    it('asks for the business uuid at the instant of the clock', function () {
        $this->plans->entitlementsOf(SubscriptionFixtures::BUSINESS_ID);

        expect($this->subscriptions->inEffectLookups)->toEqual([
            ['businessId' => SubscriptionFixtures::BUSINESS_ID, 'now' => SubscriptionFixtures::now()],
        ]);
    });
});

describe('many businesses', function () {
    beforeEach(function () {
        $this->subscriptions->store(
            SubscriptionFixtures::subscription(),
            SubscriptionFixtures::subscription(
                id: SubscriptionFixtures::OTHER_SUBSCRIPTION_ID,
                businessId: SubscriptionFixtures::OTHER_BUSINESS_ID,
                endsAt: '2026-06-10T06:00:00+00:00',
            ),
        );

        $this->requested = [
            BUSINESS_PLANS_THIRD_BUSINESS_ID,
            SubscriptionFixtures::BUSINESS_ID,
            SubscriptionFixtures::OTHER_BUSINESS_ID,
        ];
    });

    it('answers for every business asked, keyed by its uuid, in the order asked', function () {
        expect(array_keys($this->plans->entitlementsOfMany($this->requested)))->toBe($this->requested);
    });

    it('gives each business the entitlements of its own plan', function () {
        expect($this->plans->entitlementsOfMany($this->requested))->toEqual([
            BUSINESS_PLANS_THIRD_BUSINESS_ID => PlanEntitlements::free(),
            SubscriptionFixtures::BUSINESS_ID => PlanEntitlements::complete(),
            SubscriptionFixtures::OTHER_BUSINESS_ID => PlanEntitlements::free(),
        ]);
    });

    it('asks the repository once for the whole list at the instant of the clock', function () {
        $this->plans->entitlementsOfMany($this->requested);

        expect($this->subscriptions->inEffectForManyLookups)->toEqual([
            ['businessIds' => $this->requested, 'now' => SubscriptionFixtures::now()],
        ])->and($this->subscriptions->inEffectLookups)->toBe([]);
    });

    it('agrees with the single business answer for every business', function () {
        $many = $this->plans->entitlementsOfMany($this->requested);

        foreach ($this->requested as $businessId) {
            expect($many[$businessId])->toEqual($this->plans->entitlementsOf($businessId));
        }
    });

    it('answers an empty list with an empty map and no query at all', function () {
        expect($this->plans->entitlementsOfMany([]))->toBe([])
            ->and($this->subscriptions->inEffectForManyLookups)->toBe([])
            ->and($this->subscriptions->inEffectLookups)->toBe([]);
    });

    it('answers a business asked twice under its one uuid', function () {
        expect($this->plans->entitlementsOfMany([SubscriptionFixtures::BUSINESS_ID, SubscriptionFixtures::BUSINESS_ID]))
            ->toEqual([SubscriptionFixtures::BUSINESS_ID => PlanEntitlements::complete()]);
    });
});
