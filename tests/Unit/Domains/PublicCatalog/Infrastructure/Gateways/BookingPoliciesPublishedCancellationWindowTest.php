<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\PublicCatalog\Infrastructure\Gateways\BookingPoliciesPublishedCancellationWindow;
use App\Domains\PublicCatalog\ValueObjects\PublicCancellationWindow;
use Tests\Support\BookingPolicies\BookingPolicyFixtures;
use Tests\Support\BookingPolicies\FakeBookingPolicyRepository;
use Tests\Support\PublicCatalog\FakeBookingRulesAllowance;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->policies = new FakeBookingPolicyRepository;
    $this->allowance = FakeBookingRulesAllowance::onCompletePlan();

    $this->read = fn (string $businessId = PublicCatalogFixtures::BUSINESS_ID): PublicCancellationWindow => (new BookingPoliciesPublishedCancellationWindow($this->policies, $this->allowance))
        ->forBusiness($businessId);

    $this->storeFor = function (string $businessId, ?int $cancellationWindowMinutes): void {
        $this->policies->store(BookingPolicyFixtures::policy(
            businessId: $businessId,
            cancellationWindowMinutes: $cancellationWindowMinutes,
        ));
    };
});

describe('a business whose plan includes booking rules', function () {
    it('publishes the default window when it never saved a booking policy', function () {
        expect(($this->read)()->minutes)->toBe(BookingPolicy::DEFAULT_CANCELLATION_WINDOW_MINUTES)
            ->and(BookingPolicy::DEFAULT_CANCELLATION_WINDOW_MINUTES)->toBe(120);
    });

    it('publishes the window it stored', function (int $minutes) {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, $minutes);

        expect(($this->read)()->minutes)->toBe($minutes);
    })->with([
        'ninety minutes' => 90,
        'four hours' => 240,
        'the maximum of thirty days' => 43200,
    ]);

    it('publishes a zero window as the integer zero, never as a missing window', function () {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, 0);

        expect(($this->read)()->minutes)->toBe(0)->not->toBeNull();
    });

    it('publishes no window when it allows no cancellation', function () {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, null);

        $published = ($this->read)();

        expect($published)->toEqual(PublicCancellationWindow::notAllowed())
            ->and($published->minutes)->toBeNull();
    });

    it('reads the policy of the business uuid it was given', function () {
        ($this->read)(PublicCatalogFixtures::OTHER_BUSINESS_ID);

        expect($this->policies->businessIdsSeen)->toBe([PublicCatalogFixtures::OTHER_BUSINESS_ID]);
    });

    it('never publishes the window another business stored', function () {
        ($this->storeFor)(PublicCatalogFixtures::OTHER_BUSINESS_ID, null);

        expect(($this->read)(PublicCatalogFixtures::BUSINESS_ID)->minutes)
            ->toBe(BookingPolicy::DEFAULT_CANCELLATION_WINDOW_MINUTES);
    });

    it('publishes each business its own stored window', function () {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, 90);
        $this->policies->store(BookingPolicyFixtures::policy(
            id: BookingPolicyFixtures::OTHER_POLICY_ID,
            businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID,
            cancellationWindowMinutes: 0,
        ));

        expect(($this->read)(PublicCatalogFixtures::BUSINESS_ID)->minutes)->toBe(90)
            ->and(($this->read)(PublicCatalogFixtures::OTHER_BUSINESS_ID)->minutes)->toBe(0);
    });
});

describe('a business whose plan leaves booking rules out', function () {
    beforeEach(function () {
        $this->allowance = FakeBookingRulesAllowance::onFreePlan();
    });

    it('publishes the default window when it never saved a booking policy', function () {
        expect(($this->read)()->minutes)->toBe(BookingPolicy::DEFAULT_CANCELLATION_WINDOW_MINUTES);
    });

    it('publishes the default window whatever window it stored', function (?int $storedMinutes) {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, $storedMinutes);

        expect(($this->read)()->minutes)->toBe(BookingPolicy::DEFAULT_CANCELLATION_WINDOW_MINUTES);
    })->with([
        'four hours' => 240,
        'zero' => 0,
        'no cancellation allowed' => null,
    ]);

    it('never reads the stored policy', function () {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, 240);

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
    ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, 240);

    ($this->read)();

    expect($this->policies->saved)->toBe([])
        ->and($this->policies->deleted)->toBe([]);
})->with([
    'plan with booking rules' => fn () => FakeBookingRulesAllowance::onCompletePlan(),
    'plan without booking rules' => fn () => FakeBookingRulesAllowance::onFreePlan(),
]);
