<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\UseCases\EnforcePaymentGrace;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\Application\UseCaseResponse;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeBillingSubscriptions;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\RecordingDispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;
use Tests\Support\Subscriptions\SubscriptionJournal;

const GRACE_RUN_OUT_FAILED_AT = '2026-06-10T15:00:00+00:00';

const GRACE_STILL_RUNNING_FAILED_AT = '2026-06-10T15:00:01+00:00';

function pastDueSubscription(
    string $paymentFailedAt = GRACE_RUN_OUT_FAILED_AT,
    string $id = SubscriptionFixtures::SUBSCRIPTION_ID,
    string $businessId = SubscriptionFixtures::BUSINESS_ID,
    ?string $billingSubscriptionId = SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
    string $billingCustomerId = SubscriptionFixtures::BILLING_CUSTOMER_ID,
): Subscription {
    return SubscriptionFixtures::subscription(
        status: SubscriptionStatus::PastDue,
        paymentFailedAt: $paymentFailedAt,
        billingSubscriptionId: $billingSubscriptionId,
        id: $id,
        businessId: $businessId,
        billingCustomerId: $billingCustomerId,
    );
}

beforeEach(function () {
    $this->journal = new SubscriptionJournal;
    $this->subscriptions = new FakeSubscriptionRepository($this->journal);
    $this->events = new RecordingDispatcher($this->journal);
    $this->billing = new FakeBillingSubscriptions(
        SubscriptionFixtures::snapshot(status: SubscriptionStatus::Canceled, canceledAt: SubscriptionFixtures::NOW),
        SubscriptionFixtures::snapshot(
            status: SubscriptionStatus::Canceled,
            canceledAt: SubscriptionFixtures::NOW,
            subscriptionId: SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID,
            billingCustomerId: SubscriptionFixtures::OTHER_BILLING_CUSTOMER_ID,
        ),
    );

    $this->enforce = fn (): UseCaseResponse => (new EnforcePaymentGrace(
        $this->subscriptions,
        $this->billing,
        new FakeClock(SubscriptionFixtures::now()),
        $this->events,
    ))->handle();
});

describe('a past due subscription whose grace has run out', function () {
    beforeEach(function () {
        $this->subscriptions->store(pastDueSubscription());

        $this->response = ($this->enforce)();
    });

    it('reports one subscription canceled', function () {
        expect($this->response->value())->toBe(1);
    });

    it('cancels it in the billing provider right away', function () {
        expect($this->billing->calls)->toBe([
            ['operation' => 'cancelNow', 'subscriptionId' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID],
        ]);
    });

    it('saves it canceled', function () {
        expect($this->subscriptions->saved)->toHaveCount(1)
            ->and($this->subscriptions->saved[0]->status())->toBe(SubscriptionStatus::Canceled);
    });

    it('announces the end once, with the subscription and business uuids, after saving', function () {
        expect($this->events->dispatched)->toEqual([
            new SubscriptionEnded(SubscriptionFixtures::SUBSCRIPTION_ID, SubscriptionFixtures::BUSINESS_ID),
        ])->and($this->journal->entries)->toBe([
            'saved '.SubscriptionFixtures::SUBSCRIPTION_ID,
            'dispatched '.SubscriptionEnded::class,
        ]);
    });

    it('asks the repository for the rows past grace at the instant of the clock', function () {
        expect($this->subscriptions->pastPaymentGraceLookups)->toEqual([SubscriptionFixtures::now()]);
    });
});

it('cancels every subscription past its grace, each under its own billing id', function () {
    $this->subscriptions->store(
        pastDueSubscription(),
        pastDueSubscription(
            id: SubscriptionFixtures::OTHER_SUBSCRIPTION_ID,
            businessId: SubscriptionFixtures::OTHER_BUSINESS_ID,
            billingSubscriptionId: SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID,
            billingCustomerId: SubscriptionFixtures::OTHER_BILLING_CUSTOMER_ID,
        ),
    );

    expect(($this->enforce)()->value())->toBe(2)
        ->and(array_column($this->billing->calls, 'subscriptionId'))->toBe([
            SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
            SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID,
        ])
        ->and($this->events->dispatched)->toEqual([
            new SubscriptionEnded(SubscriptionFixtures::SUBSCRIPTION_ID, SubscriptionFixtures::BUSINESS_ID),
            new SubscriptionEnded(SubscriptionFixtures::OTHER_SUBSCRIPTION_ID, SubscriptionFixtures::OTHER_BUSINESS_ID),
        ]);
});

it('leaves alone a row the repository reported whose grace is still running', function () {
    $this->subscriptions->reportingPastPaymentGrace(pastDueSubscription(GRACE_STILL_RUNNING_FAILED_AT));

    expect(($this->enforce)()->value())->toBe(0)
        ->and($this->billing->calls)->toBe([])
        ->and($this->subscriptions->saved)->toBe([])
        ->and($this->events->dispatched)->toBe([]);
});

it('skips a row with no billing subscription to cancel, and still cancels the others', function () {
    $this->subscriptions->reportingPastPaymentGrace(
        pastDueSubscription(billingSubscriptionId: null),
        pastDueSubscription(
            id: SubscriptionFixtures::OTHER_SUBSCRIPTION_ID,
            businessId: SubscriptionFixtures::OTHER_BUSINESS_ID,
            billingSubscriptionId: SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID,
            billingCustomerId: SubscriptionFixtures::OTHER_BILLING_CUSTOMER_ID,
        ),
    );

    expect(($this->enforce)()->value())->toBe(1)
        ->and($this->billing->calls)->toBe([
            ['operation' => 'cancelNow', 'subscriptionId' => SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID],
        ]);
});

it('cancels nothing when no subscription is past its grace', function () {
    expect(($this->enforce)()->value())->toBe(0)
        ->and($this->billing->calls)->toBe([])
        ->and($this->events->dispatched)->toBe([]);
});
