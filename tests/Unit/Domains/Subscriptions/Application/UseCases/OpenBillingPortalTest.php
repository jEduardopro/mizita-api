<?php

declare(strict_types=1);

use App\Domains\Subscriptions\Application\Dtos\BillingPortalData;
use App\Domains\Subscriptions\Application\Dtos\OpenBillingPortalInput;
use App\Domains\Subscriptions\Application\UseCases\OpenBillingPortal;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Subscriptions\FakeBillingPortal;
use Tests\Support\Subscriptions\FakeSubscriptionRepository;
use Tests\Support\Subscriptions\SubscriptionFixtures;

const OPEN_BILLING_PORTAL_RETURN_URL = 'https://mizita.test/settings/plan';

beforeEach(function () {
    $this->subscriptions = new FakeSubscriptionRepository;
    $this->portal = new FakeBillingPortal;

    $this->open = fn (string $businessId = SubscriptionFixtures::BUSINESS_ID): UseCaseResponse => (new OpenBillingPortal(
        $this->subscriptions,
        $this->portal,
        OPEN_BILLING_PORTAL_RETURN_URL,
    ))->handle(new OpenBillingPortalInput($businessId));
});

it('returns the portal url the billing provider opened', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription());

    $portal = ($this->open)()->value();

    expect($portal)->toBeInstanceOf(BillingPortalData::class)
        ->and($portal->url)->toBe(FakeBillingPortal::URL_PREFIX.SubscriptionFixtures::BILLING_CUSTOMER_ID);
});

it('opens the portal for the billing customer of the business, returning to the plan page', function () {
    $this->subscriptions->store(
        SubscriptionFixtures::subscription(),
        SubscriptionFixtures::subscription(
            id: SubscriptionFixtures::OTHER_SUBSCRIPTION_ID,
            businessId: SubscriptionFixtures::OTHER_BUSINESS_ID,
            billingCustomerId: SubscriptionFixtures::OTHER_BILLING_CUSTOMER_ID,
        ),
    );

    ($this->open)();

    expect($this->portal->requests)->toBe([
        ['billingCustomerId' => SubscriptionFixtures::BILLING_CUSTOMER_ID, 'returnUrl' => OPEN_BILLING_PORTAL_RETURN_URL],
    ])->and($this->subscriptions->businessLookups)->toBe([SubscriptionFixtures::BUSINESS_ID]);
});

it('opens the portal for a subscription that no longer grants access, so an old card can still be fixed', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(status: SubscriptionStatus::Canceled));

    expect(($this->open)()->succeeded())->toBeTrue();
});

it('refuses a business that never had a billing customer, opening no portal', function () {
    $this->subscriptions->store(SubscriptionFixtures::subscription(businessId: SubscriptionFixtures::OTHER_BUSINESS_ID));

    $response = ($this->open)();

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('subscription_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->portal->requests)->toBe([]);
});
