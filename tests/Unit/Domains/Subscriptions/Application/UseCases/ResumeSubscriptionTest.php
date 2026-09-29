<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\ResumeSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Application\UseCases\ResumeSubscription;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeBillingSubscriptions;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\RecordingDispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const RESUME_CANCELED_AT = '2026-06-10T15:00:00+00:00';

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->billing = new FakeBillingSubscriptions(SubscriptionFixtures::snapshot());
    $this->events = new RecordingDispatcher;

    $this->resume = fn (string $businessId = SubscriptionFixtures::BUSINESS_ID): UseCaseResponse => (new ResumeSubscription(
        $this->subscriptions,
        $this->billing,
        new FakeClock(SubscriptionFixtures::now()),
        $this->events,
    ))->handle(new ResumeSubscriptionInput($businessId));
});

describe('a subscription set to end that still grants access', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(canceledAt: RESUME_CANCELED_AT));
    });

    it('asks the billing provider to resume it', function () {
        ($this->resume)();

        expect($this->billing->calls)->toBe([
            ['operation' => 'resume', 'subscriptionId' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID],
        ]);
    });

    it('returns the subscription renewing again, with no pending cancellation', function () {
        $subscription = ($this->resume)()->value();

        expect($subscription)->toBeInstanceOf(SubscriptionData::class)
            ->and($subscription->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($subscription->plan)->toBe(Plan::Complete)
            ->and($subscription->status)->toBe(SubscriptionStatus::Active)
            ->and($subscription->canceledAt)->toBeNull()
            ->and($subscription->canResume)->toBeFalse()
            ->and($subscription->canSwitchToFree)->toBeTrue();
    });

    it('saves the cleared cancellation', function () {
        ($this->resume)();

        expect($this->subscriptions->saved)->toHaveCount(1)
            ->and($this->subscriptions->saved[0]->canceledAt())->toBeNull();
    });

    it('announces nothing, because access never stopped', function () {
        ($this->resume)();

        expect($this->events->dispatched)->toBe([]);
    });
});

describe('refusals', function () {
    it('refuses a business with no subscription', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(
            canceledAt: RESUME_CANCELED_AT,
            businessId: SubscriptionFixtures::OTHER_BUSINESS_ID,
        ));

        $response = ($this->resume)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->billing->calls)->toBe([]);
    });

    it('refuses a subscription with nothing to resume from', function (Subscription $subscription) {
        $this->subscriptions->store($subscription);

        $response = ($this->resume)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_not_resumable')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    })->with([
        'renewing with no cancellation pending' => fn () => SubscriptionFixtures::subscription(),
        'canceled and over' => fn () => SubscriptionFixtures::subscription(
            status: SubscriptionStatus::Canceled,
            canceledAt: RESUME_CANCELED_AT,
        ),
        'set to end but its period already over' => fn () => SubscriptionFixtures::subscription(
            currentPeriodEndsAt: '2026-06-15T15:00:00+00:00',
            canceledAt: RESUME_CANCELED_AT,
        ),
    ]);

    it('refuses a pending cancellation with no billing subscription behind it', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(
            canceledAt: RESUME_CANCELED_AT,
            billingSubscriptionId: null,
        ));

        $response = ($this->resume)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_not_active')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('asks the billing provider nothing, saves nothing and announces nothing when it refuses', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());

        ($this->resume)();

        expect($this->billing->calls)->toBe([])
            ->and($this->subscriptions->saved)->toBe([])
            ->and($this->events->dispatched)->toBe([]);
    });
});
