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

it('shows the free plan to a business that never subscribed', function () {
    $plan = ($this->show)();

    expect($plan->plan)->toBe(Plan::Free)
        ->and($plan->entitlements)->toEqual(PlanEntitlements::free());
});

it('shows the complete plan while a subscription grants access', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription());

    $plan = ($this->show)();

    expect($plan->plan)->toBe(Plan::Complete)
        ->and($plan->entitlements)->toEqual(PlanEntitlements::complete());
});

it('keeps the complete plan through a failed renewal while the payment grace lasts', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(
        status: SubscriptionStatus::PastDue,
        paymentFailedAt: '2026-06-11T15:00:00+00:00',
    ));

    expect(($this->show)()->plan)->toBe(Plan::Complete);
});

it('falls back to free once the payment grace has run out', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(
        status: SubscriptionStatus::PastDue,
        paymentFailedAt: '2026-06-10T15:00:00+00:00',
    ));

    expect(($this->show)()->plan)->toBe(Plan::Free);
});

it('falls back to free at the end of a period set to end, with no renewal leeway', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(canceledAt: '2026-06-10T15:00:00+00:00'));
    $this->clock = new FakeClock(SubscriptionFixtures::instant(SubscriptionFixtures::PERIOD_ENDS_AT));

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

it('asks for the business uuid it was given', function () {
    ($this->show)();

    expect($this->subscriptions->businessLookups)->toBe([SubscriptionFixtures::BUSINESS_ID]);
});
