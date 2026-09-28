<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Gateways\BookingPoliciesPublishedBookingPolicy;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingPolicy;
use Tests\Support\BookingPolicies\BookingPolicyFixtures;
use Tests\Support\BookingPolicies\FakeBookingPolicyRepository;
use Tests\Support\PublicCatalog\FakeBookingRulesAllowance;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->policies = new FakeBookingPolicyRepository;
    $this->allowance = FakeBookingRulesAllowance::onCompletePlan();

    $this->read = fn (string $businessId = PublicCatalogFixtures::BUSINESS_ID): ?PublicBookingPolicy => (new BookingPoliciesPublishedBookingPolicy($this->policies, $this->allowance))
        ->forBusiness($businessId);

    $this->storeFor = function (string $businessId, ?string $policyMessage = BookingPolicyFixtures::POLICY_MESSAGE, bool $displayedOnBookingPage = true): void {
        $this->policies->store(BookingPolicyFixtures::policy(
            businessId: $businessId,
            policyMessage: $policyMessage,
            displayedOnBookingPage: $displayedOnBookingPage,
        ));
    };
});

describe('a business whose plan includes booking rules', function () {
    it('publishes the message it chose to display', function () {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID);

        expect(($this->read)())->toEqual(new PublicBookingPolicy(BookingPolicyFixtures::POLICY_MESSAGE));
    });

    it('publishes nothing when it never saved a booking policy', function () {
        expect(($this->read)())->toBeNull();
    });

    it('publishes nothing when it chose to keep the message off the booking page', function () {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, displayedOnBookingPage: false);

        expect(($this->read)())->toBeNull();
    });

    it('publishes nothing when it displays a policy with no message', function () {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, policyMessage: null);

        expect(($this->read)())->toBeNull();
    });

    it('reads the policy of the business uuid it was given', function () {
        ($this->read)(PublicCatalogFixtures::OTHER_BUSINESS_ID);

        expect($this->policies->businessIdsSeen)->toBe([PublicCatalogFixtures::OTHER_BUSINESS_ID]);
    });

    it('never publishes the message another business saved', function () {
        ($this->storeFor)(PublicCatalogFixtures::OTHER_BUSINESS_ID);

        expect(($this->read)(PublicCatalogFixtures::BUSINESS_ID))->toBeNull();
    });
});

describe('a business whose plan leaves booking rules out', function () {
    beforeEach(function () {
        $this->allowance = FakeBookingRulesAllowance::onFreePlan();
    });

    it('publishes nothing even when it saved a message to display', function () {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID);

        expect(($this->read)())->toBeNull();
    });

    it('never reads the stored policy', function () {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID);

        ($this->read)();

        expect($this->policies->businessIdsSeen)->toBe([]);
    });
});

describe('the plan it consults', function () {
    it('asks about the business uuid it was given', function (FakeBookingRulesAllowance $allowance) {
        $this->allowance = $allowance;

        ($this->read)(PublicCatalogFixtures::OTHER_BUSINESS_ID);

        expect($this->allowance->consultations)->toBe([PublicCatalogFixtures::OTHER_BUSINESS_ID]);
    })->with([
        'plan with booking rules' => fn () => FakeBookingRulesAllowance::onCompletePlan(),
        'plan without booking rules' => fn () => FakeBookingRulesAllowance::onFreePlan(),
    ]);
});

it('writes nothing back while reading', function (FakeBookingRulesAllowance $allowance) {
    $this->allowance = $allowance;
    ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID);

    ($this->read)();

    expect($this->policies->saved)->toBe([])
        ->and($this->policies->deleted)->toBe([]);
})->with([
    'plan with booking rules' => fn () => FakeBookingRulesAllowance::onCompletePlan(),
    'plan without booking rules' => fn () => FakeBookingRulesAllowance::onFreePlan(),
]);
