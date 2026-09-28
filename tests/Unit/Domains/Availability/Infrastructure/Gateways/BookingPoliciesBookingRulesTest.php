<?php

declare(strict_types=1);

use App\Domains\Availability\Contracts\BookingRulesAllowance;
use App\Domains\Availability\Infrastructure\Gateways\BookingPoliciesBookingRules;
use App\Domains\Availability\ValueObjects\SlotRules;
use App\Domains\BookingPolicies\Contracts\BookingPolicyRepository;
use App\Domains\BookingPolicies\Entities\BookingPolicy;
use Tests\Support\BookingPolicies\BookingPolicyFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->stored = null;
    $this->policyLookups = [];
    $this->allowanceLookups = [];
    $this->includesBookingRules = true;

    $this->policies = Mockery::mock(BookingPolicyRepository::class);
    $this->policies->shouldReceive('findForBusiness')->andReturnUsing(function (string $businessId): ?BookingPolicy {
        $this->policyLookups[] = $businessId;

        return $this->stored;
    });
    $this->policies->shouldNotReceive('save', 'delete');

    $allowance = Mockery::mock(BookingRulesAllowance::class);
    $allowance->shouldReceive('includesBookingRules')->andReturnUsing(function (string $businessId): bool {
        $this->allowanceLookups[] = $businessId;

        return $this->includesBookingRules;
    });

    $this->rules = new BookingPoliciesBookingRules(
        $this->policies,
        $allowance,
        new FixedIdGenerator(
            BookingPolicyFixtures::GENERATED_POLICY_ID,
            BookingPolicyFixtures::OTHER_POLICY_ID,
        ),
        new FakeClock(BookingPolicyFixtures::now()),
    );

    $this->storePolicy = function (int $leadTimeMinutes, ?int $bookingWindowMinutes, int $slotGranularityMinutes): void {
        $this->stored = BookingPolicyFixtures::policy(
            leadTimeMinutes: $leadTimeMinutes,
            bookingWindowMinutes: $bookingWindowMinutes,
            slotGranularityMinutes: $slotGranularityMinutes,
        );
    };

    $this->asTriple = static fn (SlotRules $rules): array => [
        $rules->leadTimeMinutes,
        $rules->bookingWindowMinutes,
        $rules->slotGranularityMinutes,
    ];
});

describe('a business whose plan includes booking rules', function () {
    it('hands back the lead time, window and granularity the business stored', function () {
        ($this->storePolicy)(60, 43200, 30);

        expect(($this->asTriple)($this->rules->forBusiness(FakeBusinessContext::BUSINESS_ID)))
            ->toBe([60, 43200, 30]);
    });

    it('keeps an unlimited window the business stored as unlimited', function () {
        ($this->storePolicy)(120, null, 60);

        expect(($this->asTriple)($this->rules->forBusiness(FakeBusinessContext::BUSINESS_ID)))
            ->toBe([120, null, 60]);
    });

    it('falls back to the defaults when the business stored no policy', function () {
        expect(($this->asTriple)($this->rules->forBusiness(FakeBusinessContext::BUSINESS_ID)))->toBe([
            BookingPolicy::DEFAULT_LEAD_TIME_MINUTES,
            BookingPolicy::DEFAULT_BOOKING_WINDOW_MINUTES,
            BookingPolicy::DEFAULT_SLOT_GRANULARITY_MINUTES,
        ]);
    });
});

describe('a business whose plan leaves booking rules out', function () {
    beforeEach(function () {
        $this->includesBookingRules = false;
    });

    it('answers with the platform defaults whatever the business stored', function (int $leadTimeMinutes, ?int $bookingWindowMinutes, int $slotGranularityMinutes) {
        ($this->storePolicy)($leadTimeMinutes, $bookingWindowMinutes, $slotGranularityMinutes);

        expect(($this->asTriple)($this->rules->forBusiness(FakeBusinessContext::BUSINESS_ID)))->toBe([
            BookingPolicy::DEFAULT_LEAD_TIME_MINUTES,
            BookingPolicy::DEFAULT_BOOKING_WINDOW_MINUTES,
            BookingPolicy::DEFAULT_SLOT_GRANULARITY_MINUTES,
        ]);
    })->with([
        'half hour granularity' => [60, 43200, 30],
        'full hour granularity' => [240, 10080, 60],
        'unlimited window' => [120, null, 60],
    ]);

    it('ignores a stored granularity in favour of the default quarter hour', function () {
        ($this->storePolicy)(60, 43200, 60);

        expect($this->rules->forBusiness(FakeBusinessContext::BUSINESS_ID)->slotGranularityMinutes)->toBe(15);
    });

    it('answers with the platform defaults when the business stored no policy', function () {
        expect(($this->asTriple)($this->rules->forBusiness(FakeBusinessContext::BUSINESS_ID)))->toBe([
            BookingPolicy::DEFAULT_LEAD_TIME_MINUTES,
            BookingPolicy::DEFAULT_BOOKING_WINDOW_MINUTES,
            BookingPolicy::DEFAULT_SLOT_GRANULARITY_MINUTES,
        ]);
    });

    it('never reads the policy store', function () {
        ($this->storePolicy)(60, 43200, 30);

        $this->rules->forBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($this->policyLookups)->toBe([]);
    });
});

describe('the business it reads for', function () {
    it('asks the plan and the policy store about the business it was handed', function () {
        ($this->storePolicy)(60, 43200, 30);

        $this->rules->forBusiness(BookingPolicyFixtures::OTHER_BUSINESS_ID);

        expect($this->policyLookups)->toBe([BookingPolicyFixtures::OTHER_BUSINESS_ID])
            ->and($this->allowanceLookups)->toBe([BookingPolicyFixtures::OTHER_BUSINESS_ID]);
    });

    it('asks the plan about the business it was handed when booking rules are left out', function () {
        $this->includesBookingRules = false;

        $this->rules->forBusiness(BookingPolicyFixtures::OTHER_BUSINESS_ID);

        expect($this->allowanceLookups)->toBe([BookingPolicyFixtures::OTHER_BUSINESS_ID]);
    });
});
