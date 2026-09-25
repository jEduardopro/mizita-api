<?php

declare(strict_types=1);

use App\Domains\Availability\Exceptions\StaffMemberNotFound;
use App\Domains\Availability\Infrastructure\Gateways\StaffStaffRoster;
use App\Domains\Staff\Exceptions\StaffMemberNotFound as StaffMemberMissingFromStaff;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->unknownMemberId = '01930000-0000-7000-8000-0000000000d9';

    $this->members = (new FakeStaffMemberRepository)->store(
        StaffFixtures::member(),
        StaffFixtures::member(
            id: StaffFixtures::SECOND_MEMBER_ID,
            accountId: StaffFixtures::SECOND_ACCOUNT_ID,
            businessId: StaffFixtures::OTHER_BUSINESS_ID,
        ),
    );

    $this->roster = new StaffStaffRoster($this->members);

    $this->refusalFor = function (string $businessId, string $staffMemberId): ?StaffMemberNotFound {
        try {
            $this->roster->confirmMembership($businessId, $staffMemberId);
        } catch (StaffMemberNotFound $refused) {
            return $refused;
        }

        return null;
    };
});

describe('a staff member of the business', function () {
    it('confirms the membership without complaint', function () {
        expect(fn () => $this->roster->confirmMembership(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID))
            ->not->toThrow(Throwable::class);
    });

    it('looks the staff member up inside the business it was handed', function () {
        $this->roster->confirmMembership(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

        expect($this->members->businessLookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'id' => StaffFixtures::MEMBER_ID,
        ]]);
    });

    it('confirms a member of the other business when asked inside it', function () {
        expect(fn () => $this->roster->confirmMembership(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID))
            ->not->toThrow(Throwable::class);
    });
});

describe('a staff member of another business', function () {
    it('refuses them with the availability not found failure', function () {
        expect(fn () => $this->roster->confirmMembership(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID))
            ->toThrow(StaffMemberNotFound::class);
    });

    it('refuses with a failure the transport can classify', function () {
        $refusal = ($this->refusalFor)(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('staff_member_not_found')
            ->and($refusal?->kind())->toBe(DomainFailureKind::NotFound);
    });

    it('keeps the staff domain refusal as the cause', function () {
        $refusal = ($this->refusalFor)(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

        expect($refusal?->getPrevious())->toBeInstanceOf(StaffMemberMissingFromStaff::class);
    });

    it('names the staff member and the business it was asked about', function () {
        $refusal = ($this->refusalFor)(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

        expect($refusal?->getMessage())
            ->toBe('Staff member ['.StaffFixtures::SECOND_MEMBER_ID.'] does not belong to business ['.FakeBusinessContext::BUSINESS_ID.'].');
    });
});

describe('a staff member nobody has', function () {
    it('refuses an unknown uuid with the availability not found failure', function () {
        $refusal = ($this->refusalFor)(FakeBusinessContext::BUSINESS_ID, $this->unknownMemberId);

        expect($refusal)->toBeInstanceOf(StaffMemberNotFound::class)
            ->and($refusal?->errorCode())->toBe('staff_member_not_found')
            ->and($refusal?->getPrevious())->toBeInstanceOf(StaffMemberMissingFromStaff::class);
    });
});
