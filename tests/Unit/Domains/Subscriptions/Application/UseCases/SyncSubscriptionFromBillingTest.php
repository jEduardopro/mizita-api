<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\SyncSubscriptionFromBillingInput;
use App\Domains\Subscriptions\Application\UseCases\SyncSubscriptionFromBilling;
use App\Domains\Subscriptions\Events\SubscriptionEnded;
use App\Domains\Subscriptions\Events\SubscriptionStarted;
use App\Domains\Subscriptions\ValueObjects\BillingSnapshot;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Domains\Subscriptions\ValueObjects\SubscriptionTransition;
use App\Shared\Application\UseCaseResponse;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeBillingSubscriptions;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\RecordingDispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;
use Tests\Support\Subscriptions\SubscriptionJournal;

beforeEach(function () {
    $this->journal = new SubscriptionJournal;
    $this->subscriptions = new FakeSubscriptionRepository($this->journal);
    $this->events = new RecordingDispatcher($this->journal);

    $this->sync = function (BillingSnapshot $snapshot): UseCaseResponse {
        $this->billing = new FakeBillingSubscriptions($snapshot);

        return (new SyncSubscriptionFromBilling(
            $this->subscriptions,
            $this->billing,
            new FakeClock(SubscriptionFixtures::now()),
            $this->events,
        ))->handle(new SyncSubscriptionFromBillingInput($snapshot->subscriptionId));
    };
});

describe('a subscription that starts granting access', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::opened());

        $this->response = ($this->sync)(SubscriptionFixtures::snapshot());
    });

    it('reports it started', function () {
        expect($this->response->value())->toBe(SubscriptionTransition::Started);
    });

    it('fetches the subscription it was told about from the billing provider', function () {
        expect($this->billing->calls)->toBe([
            ['operation' => 'fetch', 'subscriptionId' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID],
        ]);
    });

    it('finds the row by the billing customer of the snapshot', function () {
        expect($this->subscriptions->billingCustomerLookups)->toBe([SubscriptionFixtures::BILLING_CUSTOMER_ID]);
    });

    it('saves the row with what the billing provider reported', function () {
        expect($this->subscriptions->saved)->toHaveCount(1)
            ->and($this->subscriptions->saved[0]->status())->toBe(SubscriptionStatus::Active)
            ->and($this->subscriptions->saved[0]->billingSubscriptionId())->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID)
            ->and($this->subscriptions->saved[0]->currentPeriodEndsAt())->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::PERIOD_ENDS_AT));
    });

    it('announces the start once, with the subscription and business uuids, after saving', function () {
        expect($this->events->dispatched)->toEqual([
            new SubscriptionStarted(SubscriptionFixtures::SUBSCRIPTION_ID, SubscriptionFixtures::BUSINESS_ID),
        ])->and($this->journal->entries)->toBe([
            'saved '.SubscriptionFixtures::SUBSCRIPTION_ID,
            'dispatched '.SubscriptionStarted::class,
        ]);
    });
});

describe('a subscription that stops granting access', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());

        $this->response = ($this->sync)(SubscriptionFixtures::snapshot(
            status: SubscriptionStatus::Canceled,
            canceledAt: SubscriptionFixtures::NOW,
        ));
    });

    it('reports it ended', function () {
        expect($this->response->value())->toBe(SubscriptionTransition::Ended);
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

describe('a subscription whose access does not change', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());

        $this->response = ($this->sync)(SubscriptionFixtures::snapshot(
            currentPeriodEndsAt: SubscriptionFixtures::RENEWED_PERIOD_ENDS_AT,
        ));
    });

    it('reports it unchanged', function () {
        expect($this->response->value())->toBe(SubscriptionTransition::Unchanged);
    });

    it('still saves the renewed period', function () {
        expect($this->subscriptions->saved)->toHaveCount(1)
            ->and($this->subscriptions->saved[0]->currentPeriodEndsAt())
            ->toEqual(SubscriptionFixtures::instant(SubscriptionFixtures::RENEWED_PERIOD_ENDS_AT));
    });

    it('announces nothing', function () {
        expect($this->events->dispatched)->toBe([]);
    });
});

describe('a billing customer no business owns', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());

        $this->response = ($this->sync)(SubscriptionFixtures::snapshot(
            subscriptionId: SubscriptionFixtures::OTHER_BILLING_SUBSCRIPTION_ID,
            billingCustomerId: SubscriptionFixtures::OTHER_BILLING_CUSTOMER_ID,
        ));
    });

    it('reports nothing changed', function () {
        expect($this->response->succeeded())->toBeTrue()
            ->and($this->response->value())->toBe(SubscriptionTransition::Unchanged);
    });

    it('saves nothing and announces nothing', function () {
        expect($this->subscriptions->saved)->toBe([])
            ->and($this->events->dispatched)->toBe([]);
    });

    it('leaves the subscription of the other business untouched', function () {
        expect($this->subscriptions->forBusiness(SubscriptionFixtures::BUSINESS_ID)?->billingSubscriptionId())
            ->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);
    });
});
