<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\CancelSubscriptionPastPaymentGraceInput;
use App\Domains\Subscriptions\Application\UseCases\CancelSubscriptionPastPaymentGrace;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Domains\Subscriptions\ValueObjects\SubscriptionTransition;
use App\Shared\Application\UseCaseResponse;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeBillingSubscriptions;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\RecordingDispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;
use Tests\Support\Subscriptions\SubscriptionJournal;

const PAYMENT_FAILED_GRACE_RUN_OUT = '2026-06-10T15:00:00+00:00';

const PAYMENT_FAILED_GRACE_RUNNING = '2026-06-10T15:00:01+00:00';

function pastDueAfterFailedPayment(
    string $paymentFailedAt = PAYMENT_FAILED_GRACE_RUN_OUT,
    ?string $billingSubscriptionId = SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
    string $id = SubscriptionFixtures::SUBSCRIPTION_ID,
    string $businessId = SubscriptionFixtures::BUSINESS_ID,
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

    $this->cancel = fn (string $businessId = SubscriptionFixtures::BUSINESS_ID): UseCaseResponse => (new CancelSubscriptionPastPaymentGrace(
        $this->subscriptions,
        $this->billing,
        new FakeClock(SubscriptionFixtures::now()),
        $this->events,
    ))->handle(new CancelSubscriptionPastPaymentGraceInput($businessId));
});

describe('a subscription still past its payment grace when the job runs', function () {
    beforeEach(function () {
        $this->subscriptions->store(pastDueAfterFailedPayment());

        $this->response = ($this->cancel)();
    });

    it('reports that the subscription ended', function () {
        expect($this->response->failed())->toBeFalse()
            ->and($this->response->value())->toBe(SubscriptionTransition::Ended);
    });

    it('reloads the subscription by the business uuid', function () {
        expect($this->subscriptions->businessLookups)->toBe([SubscriptionFixtures::BUSINESS_ID]);
    });

    it('cancels it in the billing provider right away, under its billing id', function () {
        expect($this->billing->calls)->toBe([
            ['operation' => 'cancelNow', 'subscriptionId' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID],
        ]);
    });

    it('saves it canceled', function () {
        expect($this->subscriptions->saved)->toHaveCount(1)
            ->and($this->subscriptions->saved[0]->status())->toBe(SubscriptionStatus::Canceled)
            ->and($this->subscriptions->saved[0]->canceledAt())->toEqual(SubscriptionFixtures::now());
    });

    it('announces the end once, with the subscription and business uuids, after saving', function () {
        expect($this->events->dispatched)->toEqual([
            new SubscriptionEnded(SubscriptionFixtures::SUBSCRIPTION_ID, SubscriptionFixtures::BUSINESS_ID),
        ])->and($this->journal->entries)->toBe([
            'saved '.SubscriptionFixtures::SUBSCRIPTION_ID,
            'dispatched '.SubscriptionEnded::class,
        ]);
    });
});

it('cancels only the subscription of the business it was given', function () {
    $this->subscriptions->store(
        pastDueAfterFailedPayment(),
        pastDueAfterFailedPayment(
            billingSubscriptionId: SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID,
            id: SubscriptionFixtures::OTHER_SUBSCRIPTION_ID,
            businessId: SubscriptionFixtures::OTHER_BUSINESS_ID,
            billingCustomerId: SubscriptionFixtures::OTHER_BILLING_CUSTOMER_ID,
        ),
    );

    ($this->cancel)(SubscriptionFixtures::OTHER_BUSINESS_ID);

    expect($this->billing->calls)->toBe([
        ['operation' => 'cancelNow', 'subscriptionId' => SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID],
    ])->and($this->subscriptions->saved)->toHaveCount(1)
        ->and($this->subscriptions->saved[0]->businessId)->toBe(SubscriptionFixtures::OTHER_BUSINESS_ID)
        ->and($this->events->dispatched)->toEqual([
            new SubscriptionEnded(SubscriptionFixtures::OTHER_SUBSCRIPTION_ID, SubscriptionFixtures::OTHER_BUSINESS_ID),
        ]);
});

describe('a subscription that no longer needs cancelling', function () {
    it('leaves it alone and reports nothing changed', function (array $stored) {
        $this->subscriptions->store(...$stored);

        $response = ($this->cancel)();

        expect($response->failed())->toBeFalse()
            ->and($response->value())->toBe(SubscriptionTransition::Unchanged)
            ->and($this->billing->calls)->toBe([])
            ->and($this->subscriptions->saved)->toBe([])
            ->and($this->events->dispatched)->toBe([]);
    })->with([
        'the business has no subscription' => fn () => [],
        'only another business is past grace' => fn () => [pastDueAfterFailedPayment(
            billingSubscriptionId: SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID,
            id: SubscriptionFixtures::OTHER_SUBSCRIPTION_ID,
            businessId: SubscriptionFixtures::OTHER_BUSINESS_ID,
        )],
        'it carries no billing subscription id' => fn () => [pastDueAfterFailedPayment(billingSubscriptionId: null)],
        'its grace is still running' => fn () => [pastDueAfterFailedPayment(PAYMENT_FAILED_GRACE_RUNNING)],
        'the payment was recovered since it was queued' => fn () => [SubscriptionFixtures::subscription(status: SubscriptionStatus::Active)],
        'it was already canceled' => fn () => [SubscriptionFixtures::subscription(
            status: SubscriptionStatus::Canceled,
            canceledAt: PAYMENT_FAILED_GRACE_RUN_OUT,
            paymentFailedAt: PAYMENT_FAILED_GRACE_RUN_OUT,
        )],
    ]);
});

describe('when a collaborator fails', function () {
    it('lets a billing failure propagate so the job retries, saving and announcing nothing', function () {
        $this->subscriptions->store(pastDueAfterFailedPayment());
        $this->billing = new FakeBillingSubscriptions;

        expect(fn () => ($this->cancel)())->toThrow(RuntimeException::class)
            ->and($this->subscriptions->saved)->toBe([])
            ->and($this->events->dispatched)->toBe([]);
    });

    it('announces nothing when the subscription cannot be saved', function () {
        $this->subscriptions->store(pastDueAfterFailedPayment())
            ->failingOnSave(new RuntimeException('database unavailable'));

        expect(fn () => ($this->cancel)())->toThrow(RuntimeException::class, 'database unavailable')
            ->and($this->events->dispatched)->toBe([]);
    });
});
