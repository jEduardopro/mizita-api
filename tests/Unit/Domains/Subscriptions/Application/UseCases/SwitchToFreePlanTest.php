<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Application\Dtos\SwitchToFreePlanInput;
use App\Domains\Subscriptions\Application\UseCases\SwitchToFreePlan;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeBillingSubscriptions;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\RecordingDispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->billing = new FakeBillingSubscriptions(SubscriptionFixtures::snapshot(cancelAtPeriodEnd: true));
    $this->events = new RecordingDispatcher;

    $this->switch = fn (string $businessId = SubscriptionFixtures::BUSINESS_ID): UseCaseResponse => (new SwitchToFreePlan(
        $this->subscriptions,
        $this->billing,
        new FakeClock(SubscriptionFixtures::now()),
        $this->events,
    ))->handle(new SwitchToFreePlanInput($businessId));
});

describe('a subscription granting access with no cancellation pending', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());
    });

    it('asks the billing provider to cancel at the end of the period', function () {
        ($this->switch)();

        expect($this->billing->calls)->toBe([
            ['operation' => 'cancelAtPeriodEnd', 'subscriptionId' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID],
        ]);
    });

    it('keeps the complete plan until the period ends, now set to end and resumable', function () {
        $subscription = ($this->switch)()->value();

        expect($subscription)->toBeInstanceOf(SubscriptionData::class)
            ->and($subscription->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($subscription->plan)->toBe(Plan::Complete)
            ->and($subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($subscription->currentPeriodEndsAt)->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::PERIOD_ENDS_AT))
            ->and($subscription->canceledAt)->toEqual(SubscriptionFixtures::now())
            ->and($subscription->canSwitchToFree)->toBeFalse()
            ->and($subscription->canResume)->toBeTrue()
            ->and($subscription->canCheckout)->toBeFalse();
    });

    it('saves the pending cancellation', function () {
        ($this->switch)();

        expect($this->subscriptions->saved)->toHaveCount(1)
            ->and($this->subscriptions->saved[0]->canceledAt())->toEqual(SubscriptionFixtures::now());
    });

    it('announces nothing, because access has not ended yet', function () {
        ($this->switch)();

        expect($this->events->dispatched)->toBe([]);
    });

    it('announces the end once when the billing provider reports the subscription already over', function () {
        $this->billing = new FakeBillingSubscriptions(SubscriptionFixtures::snapshot(
            status: SubscriptionStatus::Canceled,
            canceledAt: SubscriptionFixtures::NOW,
        ));

        ($this->switch)();

        expect($this->events->dispatched)->toEqual([
            new SubscriptionEnded(SubscriptionFixtures::SUBSCRIPTION_ID, SubscriptionFixtures::BUSINESS_ID),
        ]);
    });
});

describe('refusals', function () {
    it('refuses a business with no subscription', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID));

        $response = ($this->switch)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->billing->calls)->toBe([]);
    });

    it('refuses a subscription that grants no access', function (Subscription $subscription) {
        $this->subscriptions->store($subscription);

        $response = ($this->switch)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_not_active')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    })->with([
        'canceled' => fn () => SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled),
        'checkout never completed' => fn () => SubscriptionFixtures::opened(),
        'active but lapsed past its leeway' => fn () => SubscriptionFixtures::subscription(currentPeriodEndsAt: '2026-06-14T15:00:00+00:00'),
        'granting access with no billing subscription behind it' => fn () => SubscriptionFixtures::subscription(billingSubscriptionId: null),
    ]);

    it('refuses a subscription already set to end with its period', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(canceledAt: '2026-06-10T15:00:00+00:00'));

        $response = ($this->switch)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_already_ending')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('asks the billing provider nothing, saves nothing and announces nothing when it refuses', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled));

        ($this->switch)();

        expect($this->billing->calls)->toBe([])
            ->and($this->subscriptions->saved)->toBe([])
            ->and($this->events->dispatched)->toBe([]);
    });
});
