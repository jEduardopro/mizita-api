<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\BookingPolicies\Infrastructure\ProvisionedCurrentBookingPolicy;
use Tests\Support\BookingPolicies\BookingPolicyFixtures;
use Tests\Support\BookingPolicies\FakeBookingPolicyRepository;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->policies = new FakeBookingPolicyRepository;
    $this->ids = new FixedIdGenerator(
        BookingPolicyFixtures::GENERATED_POLICY_ID,
        BookingPolicyFixtures::OTHER_POLICY_ID,
    );
    $this->clock = new FakeClock(BookingPolicyFixtures::now());

    $this->current = new ProvisionedCurrentBookingPolicy($this->policies, $this->ids, $this->clock);

    $this->read = fn (string $businessId = FakeBusinessContext::BUSINESS_ID): BookingPolicy => $this->current
        ->forBusiness($businessId);
});

describe('a business with no policy yet', function () {
    it('creates the policy rather than answering with nothing', function () {
        $policy = ($this->read)();

        expect($policy)->toBeInstanceOf(BookingPolicy::class)
            ->and($this->policies->saved)->toHaveCount(1)
            ->and($this->policies->saved[0])->toBe($policy);
    });

    it('gives the new policy the uuid the generator handed out and the instant the clock reads', function () {
        $policy = ($this->read)();

        expect($policy->id)->toBe(BookingPolicyFixtures::GENERATED_POLICY_ID)
            ->and($policy->id)->toMatch('/^[0-9a-f-]{36}$/i')
            ->and($policy->createdAt)->toEqual(BookingPolicyFixtures::now());
    });

    it('creates it with the defaults the domain declares', function () {
        $policy = ($this->read)();

        expect($policy->leadTime()->minutes)->toBe(0)
            ->and($policy->bookingWindow()->isUnlimited())->toBeTrue()
            ->and($policy->slotGranularity()->minutes)->toBe(15)
            ->and($policy->cancellationWindow()->minutes)->toBe(120)
            ->and($policy->policyMessage()->toString())->toBeNull()
            ->and($policy->isDisplayedOnBookingPage())->toBeFalse();
    });

    it('scopes the new policy to the business it was asked about', function () {
        $policy = ($this->read)();

        expect($policy->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->policies->businessIdsSeen)->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('creates one policy only, however often it is read', function () {
        ($this->read)();
        ($this->read)();
        ($this->read)();

        expect($this->policies->saved)->toHaveCount(1);
    });

    it('answers with the same policy on the second read as on the first', function () {
        $first = ($this->read)();
        $second = ($this->read)();

        expect($second->id)->toBe($first->id)
            ->and($second->id)->toBe(BookingPolicyFixtures::GENERATED_POLICY_ID);
    });

    it('asks the generator for no id it does not use', function () {
        ($this->read)();
        ($this->read)();

        expect($this->ids->next())->toBe(BookingPolicyFixtures::OTHER_POLICY_ID);
    });
});

describe('a business that already has a policy', function () {
    beforeEach(function () {
        $this->policies->store(BookingPolicyFixtures::policy());
    });

    it('answers with the stored policy and writes nothing', function () {
        $policy = ($this->read)();

        expect($policy->id)->toBe(BookingPolicyFixtures::POLICY_ID)
            ->and($policy->leadTime()->minutes)->toBe(60)
            ->and($this->policies->saved)->toBe([]);
    });

    it('provisions a policy for a neighbour business that has none, leaving this one alone', function () {
        $neighbour = ($this->read)(BookingPolicyFixtures::OTHER_BUSINESS_ID);

        expect($neighbour->id)->toBe(BookingPolicyFixtures::GENERATED_POLICY_ID)
            ->and($neighbour->businessId)->toBe(BookingPolicyFixtures::OTHER_BUSINESS_ID)
            ->and($this->policies->saved)->toHaveCount(1)
            ->and(($this->read)()->id)->toBe(BookingPolicyFixtures::POLICY_ID);
    });
});
