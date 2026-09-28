<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\BusinessPlanData;
use App\Domains\Subscriptions\Application\Dtos\ShowBusinessPlanInput;
use App\Domains\Subscriptions\Application\UseCases\ShowBusinessPlan;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\PlanEntitlements;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->clock = new FakeClock(SubscriptionFixtures::now());

    $this->show = fn (string $businessId = SubscriptionFixtures::BUSINESS_ID): BusinessPlanData => (new ShowBusinessPlan($this->subscriptions, $this->clock))
        ->handle(new ShowBusinessPlanInput($businessId))
        ->value();
});

it('shows the free plan with no end to a business that never subscribed', function () {
    $plan = ($this->show)();

    expect($plan->plan)->toBe(Plan::Free)
        ->and($plan->endsAt)->toBeNull()
        ->and($plan->entitlements)->toEqual(PlanEntitlements::free());
});

it('shows the complete plan and its end while a subscription is in effect', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription());

    $plan = ($this->show)();

    expect($plan->plan)->toBe(Plan::Complete)
        ->and($plan->endsAt?->format(DATE_ATOM))->toBe(SubscriptionFixtures::ENDS_AT)
        ->and($plan->entitlements)->toEqual(PlanEntitlements::complete());
});

it('shows no end for an open-ended subscription', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(endsAt: null));

    expect(($this->show)()->endsAt)->toBeNull()
        ->and(($this->show)()->plan)->toBe(Plan::Complete);
});

it('falls back to free the instant the period ends, before any expiry job runs', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription());
    $this->clock = new FakeClock(SubscriptionFixtures::instant(SubscriptionFixtures::ENDS_AT));

    expect(($this->show)()->plan)->toBe(Plan::Free);
});

it('falls back to free once the subscription is canceled', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled));

    expect(($this->show)()->plan)->toBe(Plan::Free);
});

it('never shows a business another business\'s subscription', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID));

    expect(($this->show)()->plan)->toBe(Plan::Free)
        ->and(($this->show)(SubscriptionFixtures::OTHER_BUSINESS_ID)->plan)->toBe(Plan::Complete);
});

it('asks for the business uuid it was given at the instant of the clock', function () {
    ($this->show)();

    expect($this->subscriptions->inEffectLookups)->toEqual([
        ['businessId' => SubscriptionFixtures::BUSINESS_ID, 'now' => SubscriptionFixtures::now()],
    ]);
});
