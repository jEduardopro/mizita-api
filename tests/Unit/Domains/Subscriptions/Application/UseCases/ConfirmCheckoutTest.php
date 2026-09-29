<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\ConfirmCheckoutInput;
use App\Domains\Subscriptions\Application\Dtos\SubscriptionData;
use App\Domains\Subscriptions\Application\UseCases\ConfirmCheckout;
use App\Domains\Subscriptions\Events\SubscriptionStarted;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\Subscriptions\FakeBillingCheckout;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\RecordingDispatcher;
use Tests\Support\Subscriptions\SubscriptionFixtures;
use Tests\Support\Subscriptions\SubscriptionJournal;

beforeEach(function () {
    $this->journal = new SubscriptionJournal;
    $this->subscriptions = new FakeSubscriptionRepository($this->journal);
    $this->checkout = new FakeBillingCheckout;
    $this->events = new RecordingDispatcher($this->journal);

    $this->confirm = fn (
        string $sessionId = FakeBillingCheckout::SESSION_ID,
        string $businessId = SubscriptionFixtures::BUSINESS_ID,
    ): UseCaseResponse => (new ConfirmCheckout(
        $this->subscriptions,
        $this->checkout,
        new FakeClock(SubscriptionFixtures::now()),
        $this->events,
    ))->handle(new ConfirmCheckoutInput($businessId, $sessionId));
});

describe('a checkout whose subscription the billing provider already created', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::opened());
        $this->checkout->withSession(
            FakeBillingCheckout::SESSION_ID,
            SubscriptionFixtures::BILLING_CUSTOMER_ID,
            SubscriptionFixtures::snapshot(),
        );
    });

    it('returns the subscription as it now stands', function () {
        $subscription = ($this->confirm)()->value();

        expect($subscription)->toBeInstanceOf(SubscriptionData::class)
            ->and($subscription->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
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

    it('saves the synced subscription with the billing subscription id', function () {
        ($this->confirm)();

        expect($this->subscriptions->saved)->toHaveCount(1)
            ->and($this->subscriptions->saved[0]->billingSubscriptionId())->toBe(SubscriptionFixtures::BILLING_SUBSCRIPTION_ID);
    });

    it('announces the start once, with the subscription and business uuids', function () {
        ($this->confirm)();

        expect($this->events->dispatched)->toEqual([
            new SubscriptionStarted(SubscriptionFixtures::SUBSCRIPTION_ID, SubscriptionFixtures::BUSINESS_ID),
        ]);
    });

    it('announces the start only after the subscription is saved', function () {
        ($this->confirm)();

        expect($this->journal->entries)->toBe([
            'saved '.SubscriptionFixtures::SUBSCRIPTION_ID,
            'dispatched '.SubscriptionStarted::class,
        ]);
    });

    it('asks about the session on behalf of the billing customer of the business', function () {
        ($this->confirm)();

        expect($this->checkout->lookups)->toBe([
            ['sessionId' => FakeBillingCheckout::SESSION_ID, 'billingCustomerId' => SubscriptionFixtures::BILLING_CUSTOMER_ID],
        ])->and($this->subscriptions->businessLookups)->toBe([SubscriptionFixtures::BUSINESS_ID]);
    });

    it('announces nothing when the webhook already synced the same subscription', function () {
        ($this->confirm)();
        $this->events->dispatched = [];

        $response = ($this->confirm)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->events->dispatched)->toBe([]);
    });
});

describe('a checkout the billing provider has not turned into a subscription yet', function () {
    beforeEach(function () {
        $this->subscriptions->store(SubscriptionFixtures::opened());
        $this->checkout->withSession(FakeBillingCheckout::SESSION_ID, SubscriptionFixtures::BILLING_CUSTOMER_ID, null);
    });

    it('returns the subscription unchanged, still open to checkout', function () {
        $subscription = ($this->confirm)()->value();

        expect($subscription->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($subscription->status)->toBe(SubscriptionStatus::Incomplete)
            ->and($subscription->plan)->toBe(Plan::Free)
            ->and($subscription->canCheckout)->toBeTrue();
    });

    it('saves nothing and announces nothing', function () {
        ($this->confirm)();

        expect($this->subscriptions->saved)->toBe([])
            ->and($this->events->dispatched)->toBe([]);
    });
});

describe('a session that is not the business\'s to confirm', function () {
    it('refuses a session that belongs to another billing customer', function () {
        $this->subscriptions->store(SubscriptionFixtures::opened());
        $this->checkout->withSession(
            FakeBillingCheckout::SESSION_ID,
            SubscriptionFixtures::OTHER_BILLING_CUSTOMER_ID,
            SubscriptionFixtures::snapshot(billingCustomerId: SubscriptionFixtures::OTHER_BILLING_CUSTOMER_ID),
        );

        $response = ($this->confirm)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('checkout_session_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->subscriptions->saved)->toBe([])
            ->and($this->events->dispatched)->toBe([]);
    });

    it('refuses a business with no subscription row without asking the billing provider', function () {
        $this->subscriptions->store(SubscriptionFixtures::opened(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID));

        $response = ($this->confirm)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('checkout_session_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->checkout->lookups)->toBe([]);
    });

    it('refuses a malformed session id without reading any subscription', function (string $sessionId) {
        $response = ($this->confirm)($sessionId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('checkout_session_not_found')
            ->and($this->subscriptions->businessLookups)->toBe([])
            ->and($this->checkout->lookups)->toBe([]);
    })->with([
        'empty' => '',
        'not a checkout session' => SubscriptionFixtures::BILLING_SUBSCRIPTION_ID,
        'a path traversal' => 'cs_../../v1/customers',
        'too long' => 'cs_'.str_repeat('a', 253),
    ]);
});
