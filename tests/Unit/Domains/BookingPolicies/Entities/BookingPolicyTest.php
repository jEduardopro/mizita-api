<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\Entities\BookingPolicy;
use App\Domains\BookingPolicies\ValueObjects\BookingWindow;
use App\Domains\BookingPolicies\ValueObjects\CancellationWindow;
use App\Domains\BookingPolicies\ValueObjects\LeadTime;
use App\Domains\BookingPolicies\ValueObjects\PolicyMessage;
use App\Domains\BookingPolicies\ValueObjects\SlotGranularity;
use Tests\Support\BookingPolicies\BookingPolicyFixtures;
use Tests\Support\FakeBusinessContext;

describe('the policy a business starts with', function () {
    beforeEach(function () {
        $this->policy = BookingPolicy::withDefaults(
            BookingPolicyFixtures::POLICY_ID,
            FakeBusinessContext::BUSINESS_ID,
            BookingPolicyFixtures::now(),
        );
    });

    it('lets a customer book with no notice at all', function () {
        expect($this->policy->leadTime()->minutes)->toBe(0);
    });

    it('sets no horizon, so a customer books as far ahead as they like', function () {
        expect($this->policy->bookingWindow()->isUnlimited())->toBeTrue()
            ->and($this->policy->bookingWindow()->minutes())->toBeNull();
    });

    it('offers slots every quarter of an hour', function () {
        expect($this->policy->slotGranularity()->minutes)->toBe(15);
    });

    it('allows a cancellation up to two hours before the appointment', function () {
        expect($this->policy->cancellationWindow()->minutes)->toBe(120)
            ->and($this->policy->cancellationWindow()->isAllowed())->toBeTrue();
    });

    it('publishes no policy message and keeps the policy off the booking page', function () {
        expect($this->policy->policyMessage()->toString())->toBeNull()
            ->and($this->policy->isDisplayedOnBookingPage())->toBeFalse();
    });

    it('carries the identity and the business it was created for', function () {
        expect($this->policy->id)->toBe(BookingPolicyFixtures::POLICY_ID)
            ->and($this->policy->id)->toMatch('/^[0-9a-f-]{36}$/i')
            ->and($this->policy->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->policy->createdAt)->toEqual(BookingPolicyFixtures::now());
    });
});

describe('revising the policy', function () {
    beforeEach(function () {
        $this->policy = BookingPolicyFixtures::policy();

        $this->revise = function (
            int $leadTimeMinutes = 30,
            ?int $bookingWindowMinutes = 1440,
            int $slotGranularityMinutes = 15,
            ?int $cancellationWindowMinutes = 60,
            ?string $policyMessage = 'Avisa con una hora.',
        ): void {
            $this->policy->revise(
                leadTime: LeadTime::ofMinutes($leadTimeMinutes),
                bookingWindow: $bookingWindowMinutes === null
                    ? BookingWindow::unlimited()
                    : BookingWindow::ofMinutes($bookingWindowMinutes),
                slotGranularity: SlotGranularity::ofMinutes($slotGranularityMinutes),
                cancellationWindow: $cancellationWindowMinutes === null
                    ? CancellationWindow::notAllowed()
                    : CancellationWindow::ofMinutes($cancellationWindowMinutes),
                policyMessage: PolicyMessage::fromString($policyMessage),
            );
        };
    });

    it('replaces every rule the business submitted', function () {
        ($this->revise)();

        expect($this->policy->leadTime()->minutes)->toBe(30)
            ->and($this->policy->bookingWindow()->minutes())->toBe(1440)
            ->and($this->policy->slotGranularity()->minutes)->toBe(15)
            ->and($this->policy->cancellationWindow()->minutes)->toBe(60)
            ->and($this->policy->policyMessage()->toString())->toBe('Avisa con una hora.');
    });

    it('lifts the horizon when the business no longer bounds it', function () {
        ($this->revise)(bookingWindowMinutes: null);

        expect($this->policy->bookingWindow()->isUnlimited())->toBeTrue();
    });

    it('closes cancellation altogether when the business allows none', function () {
        ($this->revise)(cancellationWindowMinutes: null);

        expect($this->policy->cancellationWindow()->isAllowed())->toBeFalse()
            ->and($this->policy->cancellationWindow()->minutes)->toBeNull();
    });

    it('clears the message when the business writes nothing', function () {
        ($this->revise)(policyMessage: null);

        expect($this->policy->policyMessage()->toString())->toBeNull();
    });

    it('touches neither the identity nor the business nor the creation instant', function () {
        ($this->revise)();

        expect($this->policy->id)->toBe(BookingPolicyFixtures::POLICY_ID)
            ->and($this->policy->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->policy->createdAt)->toEqual(BookingPolicyFixtures::now());
    });

    it('leaves the booking page toggle exactly where it was', function (bool $displayed) {
        $policy = BookingPolicyFixtures::policy(displayedOnBookingPage: $displayed);

        $policy->revise(
            leadTime: LeadTime::ofMinutes(30),
            bookingWindow: BookingWindow::unlimited(),
            slotGranularity: SlotGranularity::ofMinutes(15),
            cancellationWindow: CancellationWindow::notAllowed(),
            policyMessage: PolicyMessage::none(),
        );

        expect($policy->isDisplayedOnBookingPage())->toBe($displayed);
    })->with([
        'shown' => true,
        'hidden' => false,
    ]);
});

describe('showing the policy on the booking page', function () {
    it('shows a policy the business had hidden', function () {
        $policy = BookingPolicyFixtures::policy(displayedOnBookingPage: false);

        $policy->displayOnBookingPage();

        expect($policy->isDisplayedOnBookingPage())->toBeTrue();
    });

    it('hides a policy the business had shown', function () {
        $policy = BookingPolicyFixtures::policy(displayedOnBookingPage: true);

        $policy->hideFromBookingPage();

        expect($policy->isDisplayedOnBookingPage())->toBeFalse();
    });

    it('stays where it is when asked for the state it already holds', function () {
        $policy = BookingPolicyFixtures::policy(displayedOnBookingPage: true);

        $policy->displayOnBookingPage();

        expect($policy->isDisplayedOnBookingPage())->toBeTrue();
    });

    it('changes nothing but the toggle', function () {
        $policy = BookingPolicyFixtures::policy();

        $policy->hideFromBookingPage();

        expect($policy->leadTime()->minutes)->toBe(60)
            ->and($policy->bookingWindow()->minutes())->toBe(43200)
            ->and($policy->slotGranularity()->minutes)->toBe(30)
            ->and($policy->cancellationWindow()->minutes)->toBe(240)
            ->and($policy->policyMessage()->toString())->toBe(BookingPolicyFixtures::POLICY_MESSAGE);
    });
});

it('restores a policy from persistence exactly as the row held it', function () {
    $policy = BookingPolicyFixtures::policy(
        leadTimeMinutes: 7,
        bookingWindowMinutes: null,
        slotGranularityMinutes: 7,
        cancellationWindowMinutes: null,
        policyMessage: null,
        displayedOnBookingPage: false,
    );

    expect($policy->leadTime()->minutes)->toBe(7)
        ->and($policy->bookingWindow()->isUnlimited())->toBeTrue()
        ->and($policy->slotGranularity()->minutes)->toBe(7)
        ->and($policy->cancellationWindow()->isAllowed())->toBeFalse()
        ->and($policy->policyMessage()->toString())->toBeNull()
        ->and($policy->isDisplayedOnBookingPage())->toBeFalse();
});

it('belongs to the business it was created for, never to another', function () {
    $policy = BookingPolicy::withDefaults(
        BookingPolicyFixtures::OTHER_POLICY_ID,
        BookingPolicyFixtures::OTHER_BUSINESS_ID,
        BookingPolicyFixtures::now(),
    );

    expect($policy->businessId)->toBe(BookingPolicyFixtures::OTHER_BUSINESS_ID)
        ->and($policy->businessId)->not->toBe(FakeBusinessContext::BUSINESS_ID);
});
