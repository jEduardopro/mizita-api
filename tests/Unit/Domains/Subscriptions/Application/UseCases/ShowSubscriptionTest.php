<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\ShowSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Application\UseCases\ShowSubscription;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;

    $this->show = fn (string $businessId = SubscriptionFixtures::BUSINESS_ID): SubscriptionData => (new ShowSubscription(
        $this->subscriptions,
        new FakeClock(SubscriptionFixtures::now()),
    ))->handle(new ShowSubscriptionInput($businessId))->value();
});

it('shows the free plan, open to checkout and with no billing to manage, to a business that never subscribed', function () {
    $subscription = ($this->show)();

    expect($subscription->id)->toBeNull()
        ->and($subscription->plan)->toBe(Plan::Free)
        ->and($subscription->status)->toBeNull()
        ->and($subscription->startedAt)->toBeNull()
        ->and($subscription->currentPeriodEndsAt)->toBeNull()
        ->and($subscription->canceledAt)->toBeNull()
        ->and($subscription->paymentGraceEndsAt)->toBeNull()
        ->and($subscription->canCheckout)->toBeTrue()
        ->and($subscription->canSwitchToFree)->toBeFalse()
        ->and($subscription->canResume)->toBeFalse()
        ->and($subscription->canManageBilling)->toBeFalse();
});

it('shows a subscription granting access field by field', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription());

    $subscription = ($this->show)();

    expect($subscription->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
        ->and($subscription->plan)->toBe(Plan::Complete)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->startedAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::STARTED_AT))
        ->and($subscription->currentPeriodEndsAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::PERIOD_ENDS_AT))
        ->and($subscription->canceledAt)->toBeNull()
        ->and($subscription->paymentGraceEndsAt)->toBeNull()
        ->and($subscription->canCheckout)->toBeFalse()
        ->and($subscription->canSwitchToFree)->toBeTrue()
        ->and($subscription->canResume)->toBeFalse()
        ->and($subscription->canManageBilling)->toBeTrue();
});

it('shows when the payment grace of a failed renewal ends', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(
        status: SubscriptionStatus::PastDue,
        paymentFailedAt: '2026-06-14T09:30:00+00:00',
    ));

    $subscription = ($this->show)();

    expect($subscription->plan)->toBe(Plan::Complete)
        ->and($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->paymentGraceEndsAt?->format(DATE_ATOM))->toBe('2026-06-19T09:30:00+00:00');
});

it('shows a lapsed subscription on the free plan, open to checkout and still managing its billing', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(
        status: SubscriptionStatus::Canceled,
        canceledAt: '2026-06-10T15:00:00+00:00',
    ));

    $subscription = ($this->show)();

    expect($subscription->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
        ->and($subscription->plan)->toBe(Plan::Free)
        ->and($subscription->status)->toBe(SubscriptionStatus::Canceled)
        ->and($subscription->canCheckout)->toBeTrue()
        ->and($subscription->canResume)->toBeFalse()
        ->and($subscription->canManageBilling)->toBeTrue();
});

it('never shows a business the subscription of another', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID));

    expect(($this->show)()->id)->toBeNull()
        ->and(($this->show)(SubscriptionFixtures::OTHER_BUSINESS_ID)->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID);
});
