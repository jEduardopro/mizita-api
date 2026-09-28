<?php

declare(strict_types=1);

use App\Domains\Appointments\Infrastructure\Gateways\BookingPoliciesCancellationPolicy;
use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\Entities\BookingPolicy;
use Tests\Support\Appointments\FakeBookingPreferencesAllowance;
use Tests\Support\BookingPolicies\BookingPolicyFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

const CANCELLATION_POLICY_DEFAULT_MINUTES = 120;

const CANCELLATION_POLICY_STORED_MINUTES = 1440;

beforeEach(function () {
    $this->stored = null;
    $this->lookups = [];

    $this->policies = Mockery::mock(BookingPolicyRepository::class);
    $this->policies->shouldReceive('findForBusiness')->andReturnUsing(function (string $businessId): ?BookingPolicy {
        $this->lookups[] = $businessId;

        return $this->stored;
    });

    $this->gatewayFor = fn (FakeBookingPreferencesAllowance $allowance) => new BookingPoliciesCancellationPolicy(
        $this->policies,
        $allowance,
        new FixedIdGenerator(BookingPolicyFixtures::GENERATED_POLICY_ID),
        new FakeClock(BookingPolicyFixtures::now()),
    );
});

describe('on the free plan', function () {
    beforeEach(function () {
        $this->allowance = FakeBookingPreferencesAllowance::free();
        $this->gateway = ($this->gatewayFor)($this->allowance);
    });

    it('applies the platform default window whatever the business stored', function (?int $storedMinutes) {
        $this->stored = BookingPolicyFixtures::policy(cancellationWindowMinutes: $storedMinutes);

        $rule = $this->gateway->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($rule->isAllowed())->toBeTrue()
            ->and($rule->minutes)->toBe(CANCELLATION_POLICY_DEFAULT_MINUTES);
    })->with([
        'a longer stored window' => [CANCELLATION_POLICY_STORED_MINUTES],
        'a stored not allowed window' => [null],
        'a stored zero window' => [0],
    ]);

    it('applies the platform default window when the business stored no policy', function () {
        expect($this->gateway->forBusiness(FakeBusinessContext::BUSINESS_ID)->minutes)
            ->toBe(CANCELLATION_POLICY_DEFAULT_MINUTES);
    });

    it('never reads the stored policy', function () {
        $this->stored = BookingPolicyFixtures::policy(cancellationWindowMinutes: CANCELLATION_POLICY_STORED_MINUTES);

        $this->gateway->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($this->lookups)->toBe([]);
    });

    it('asks the allowance about that business uuid', function () {
        $this->gateway->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($this->allowance->askedBusinessIds)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});

describe('on the complete plan', function () {
    beforeEach(function () {
        $this->allowance = FakeBookingPreferencesAllowance::complete();
        $this->gateway = ($this->gatewayFor)($this->allowance);
    });

    it('applies the stored window', function () {
        $this->stored = BookingPolicyFixtures::policy(cancellationWindowMinutes: CANCELLATION_POLICY_STORED_MINUTES);

        $rule = $this->gateway->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($rule->isAllowed())->toBeTrue()
            ->and($rule->minutes)->toBe(CANCELLATION_POLICY_STORED_MINUTES);
    });

    it('applies a stored zero window as allowed up to the start', function () {
        $this->stored = BookingPolicyFixtures::policy(cancellationWindowMinutes: 0);

        $rule = $this->gateway->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($rule->isAllowed())->toBeTrue()
            ->and($rule->minutes)->toBe(0);
    });

    it('refuses every change when the stored window is not allowed', function () {
        $this->stored = BookingPolicyFixtures::policy(cancellationWindowMinutes: null);

        $rule = $this->gateway->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($rule->isAllowed())->toBeFalse()
            ->and($rule->minutes)->toBeNull();
    });

    it('applies the platform default window when the business stored no policy', function () {
        $rule = $this->gateway->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($rule->isAllowed())->toBeTrue()
            ->and($rule->minutes)->toBe(CANCELLATION_POLICY_DEFAULT_MINUTES);
    });

    it('reads the stored policy of that business uuid exactly once', function () {
        $this->gateway->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($this->lookups)->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and($this->allowance->askedBusinessIds)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });
});
