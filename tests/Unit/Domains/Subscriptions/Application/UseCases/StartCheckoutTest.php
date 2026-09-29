<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\CheckoutSessionData;
use App\Domains\Subscriptions\Application\Dtos\StartCheckoutInput;
use App\Domains\Subscriptions\Application\UseCases\StartCheckout;
use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\Exceptions\CheckoutAlreadyStarted;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Subscriptions\FakeBillingCheckout;
use Tests\Support\Subscriptions\FakeBillingContacts;
use Tests\Support\Subscriptions\FakeBillingCustomers;
use Tests\Support\Subscriptions\FakePlanCatalog;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const START_CHECKOUT_RETURN_URL = 'https://mizita.test/settings/plan?session_id={CHECKOUT_SESSION_ID}';

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->plans = new FakePlanCatalog(
        SubscriptionFixtures::offer(),
        SubscriptionFixtures::offer(
            trialDays: SubscriptionFixtures::TRIAL_DAYS,
            id: SubscriptionFixtures::OTHER_PLAN_ID,
            billingPriceId: SubscriptionFixtures::OTHER_BILLING_PRICE_ID,
        ),
    );
    $this->contacts = new FakeBillingContacts(SubscriptionFixtures::contact());
    $this->customers = new FakeBillingCustomers;
    $this->checkout = new FakeBillingCheckout;

    $this->start = fn (
        string $planId = SubscriptionFixtures::PLAN_ID,
        string $businessId = SubscriptionFixtures::BUSINESS_ID,
    ): UseCaseResponse => (new StartCheckout(
        $this->subscriptions,
        $this->plans,
        $this->contacts,
        $this->customers,
        $this->checkout,
        new FixedIdGenerator(SubscriptionFixtures::GENERATED_SUBSCRIPTION_ID),
        new FakeClock(SubscriptionFixtures::now()),
        START_CHECKOUT_RETURN_URL,
    ))->handle(new StartCheckoutInput($businessId, $planId));
});

describe('a business that never had a subscription', function () {
    it('hands back the checkout session the billing provider opened', function () {
        $session = ($this->start)()->value();

        expect($session)->toBeInstanceOf(CheckoutSessionData::class)
            ->and($session->sessionId)->toBe(FakeBillingCheckout::SESSION_ID)
            ->and($session->clientSecret)->toBe(FakeBillingCheckout::CLIENT_SECRET);
    });

    it('creates a billing customer for the owner of that business', function () {
        ($this->start)();

        expect($this->contacts->lookups)->toBe([SubscriptionFixtures::BUSINESS_ID])
            ->and($this->customers->created)->toEqual([SubscriptionFixtures::contact()]);
    });

    it('saves an incomplete subscription under a new uuid, tied to the new billing customer and the chosen plan', function () {
        ($this->start)();

        $saved = $this->subscriptions->saved;

        expect($saved)->toHaveCount(1)
            ->and($saved[0]->id)->toBe(SubscriptionFixtures::GENERATED_SUBSCRIPTION_ID)
            ->and($saved[0]->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID)
            ->and($saved[0]->billingCustomerId)->toBe(SubscriptionFixtures::CREATED_BILLING_CUSTOMER_ID)
            ->and($saved[0]->planId())->toBe(SubscriptionFixtures::PLAN_ID)
            ->and($saved[0]->plan())->toBe(Plan::Complete)
            ->and($saved[0]->status())->toBe(SubscriptionStatus::Incomplete)
            ->and($saved[0]->billingSubscriptionId())->toBeNull()
            ->and($saved[0]->createdAt)->toEqual(SubscriptionFixtures::now());
    });

    it('asks the billing provider for a checkout of the plan price, for that business and customer, returning to the plan page', function () {
        ($this->start)();

        expect($this->checkout->started)->toHaveCount(1)
            ->and($this->checkout->started[0]->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID)
            ->and($this->checkout->started[0]->billingCustomerId)->toBe(SubscriptionFixtures::CREATED_BILLING_CUSTOMER_ID)
            ->and($this->checkout->started[0]->billingPriceId)->toBe(SubscriptionFixtures::BILLING_PRICE_ID)
            ->and($this->checkout->started[0]->returnUrl)->toBe(START_CHECKOUT_RETURN_URL);
    });

    it('asks for no trial when the plan offers none', function () {
        ($this->start)();

        expect($this->checkout->started[0]->trialDays)->toBeNull();
    });

    it('asks for the trial the plan offers', function () {
        ($this->start)(SubscriptionFixtures::OTHER_PLAN_ID);

        expect($this->checkout->started[0]->trialDays)->toBe(SubscriptionFixtures::TRIAL_DAYS)
            ->and($this->checkout->started[0]->billingPriceId)->toBe(SubscriptionFixtures::OTHER_BILLING_PRICE_ID);
    });

    it('ignores the subscription another business holds', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID));

        $response = ($this->start)();

        expect($response->succeeded())->toBeTrue()
            ->and($this->subscriptions->businessLookups)->toBe([SubscriptionFixtures::BUSINESS_ID])
            ->and($this->subscriptions->saved[0]->businessId)->toBe(SubscriptionFixtures::BUSINESS_ID);
    });
});

describe('a business that already has a subscription row', function () {
    it('reuses the billing customer of a lapsed subscription instead of creating another', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled));

        ($this->start)();

        expect($this->customers->created)->toBe([])
            ->and($this->contacts->lookups)->toBe([])
            ->and($this->subscriptions->saved)->toHaveCount(1)
            ->and($this->subscriptions->saved[0]->id)->toBe(SubscriptionFixtures::SUBSCRIPTION_ID)
            ->and($this->checkout->started[0]->billingCustomerId)->toBe(SubscriptionFixtures::BILLING_CUSTOMER_ID);
    });

    it('moves the row onto the plan chosen this time', function () {
        $this->subscriptions->store(SubscriptionFixtures::opened());

        ($this->start)(SubscriptionFixtures::OTHER_PLAN_ID);

        expect($this->subscriptions->saved[0]->planId())->toBe(SubscriptionFixtures::OTHER_PLAN_ID);
    });

    it('never offers a trial again to a business that already started a subscription once', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled));

        ($this->start)(SubscriptionFixtures::OTHER_PLAN_ID);

        expect($this->checkout->started[0]->trialDays)->toBeNull();
    });

    it('still offers the trial to a business whose earlier checkout never completed', function () {
        $this->subscriptions->store(SubscriptionFixtures::opened());

        ($this->start)(SubscriptionFixtures::OTHER_PLAN_ID);

        expect($this->checkout->started[0]->trialDays)->toBe(SubscriptionFixtures::TRIAL_DAYS);
    });
});

describe('refusals', function () {
    it('refuses a business whose subscription still grants access', function (Subscription $subscription) {
        $this->subscriptions->store($subscription);

        $response = ($this->start)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('subscription_already_active')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    })->with([
        'active' => fn () => SubscriptionFixtures::subscription(),
        'trialing' => fn () => SubscriptionFixtures::subscription(status: SubscriptionStatus::Trialing),
        'past due within its grace' => fn () => SubscriptionFixtures::subscription(
            status: SubscriptionStatus::PastDue,
            paymentFailedAt: '2026-06-14T15:00:00+00:00',
        ),
        'set to end with its period' => fn () => SubscriptionFixtures::subscription(canceledAt: '2026-06-10T15:00:00+00:00'),
    ]);

    it('opens no checkout and saves nothing for a business that already has access', function () {
        $this->subscriptions->store(SubscriptionFixtures::subscription());

        ($this->start)();

        expect($this->subscriptions->saved)->toBe([])
            ->and($this->checkout->started)->toBe([])
            ->and($this->customers->created)->toBe([]);
    });

    it('refuses a plan the catalog does not offer, touching nothing', function () {
        $response = ($this->start)('01930000-0000-7000-8000-00000000d0ff');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_subscription_plan')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->subscriptions->businessLookups)->toBe([])
            ->and($this->customers->created)->toBe([])
            ->and($this->checkout->started)->toBe([]);
    });

    it('refuses a plan id that is not a uuid without asking the catalog', function (string $planId) {
        $response = ($this->start)($planId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_subscription_plan')
            ->and($this->plans->lookups)->toBe([]);
    })->with([
        'empty' => '',
        'whitespace' => '   ',
        'an int key' => '1',
        'a stripe price id' => SubscriptionFixtures::BILLING_PRICE_ID,
    ]);

    it('refuses a business whose owner cannot be found, creating no billing customer', function () {
        $response = ($this->start)(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->customers->created)->toBe([])
            ->and($this->subscriptions->saved)->toBe([])
            ->and($this->checkout->started)->toBe([]);
    });

    it('reports a checkout started concurrently for the same business, opening no session', function () {
        $this->subscriptions->failingOnSave(CheckoutAlreadyStarted::forBusiness(SubscriptionFixtures::BUSINESS_ID));

        $response = ($this->start)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('checkout_already_started')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->checkout->started)->toBe([]);
    });
});
