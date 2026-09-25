<?php

declare(strict_types=1);

use App\Domains\Accounts\Contracts\TeamMemberships;
use App\Domains\Accounts\Exceptions\AccountHasUpcomingAppointments;
use App\Domains\Accounts\Infrastructure\Gateways\StaffTeamMemberships;
use App\Domains\Staff\Application\UseCases\CheckAccountMembershipsRemoval;
use App\Domains\Staff\Application\UseCases\RemoveAccountMemberships;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeUpcomingAppointments;
use Tests\Support\Staff\StaffFixtures;
use Tests\Support\Staff\StaffJournal;

beforeEach(function () {
    $this->transactions = new FakeTransactionManager;
    $this->journal = new StaffJournal($this->transactions);
    $this->members = (new FakeStaffMemberRepository($this->journal))->store(
        StaffFixtures::member(id: StaffFixtures::MEMBER_ID, businessId: FakeBusinessContext::BUSINESS_ID, role: StaffRole::Owner),
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
    );
    $this->appointments = new FakeUpcomingAppointments;
    $clock = new FakeClock(new DateTimeImmutable('2026-10-25T00:30:00+00:00'));

    $this->memberships = new StaffTeamMemberships(
        $this->members,
        new CheckAccountMembershipsRemoval($this->members, $this->appointments, $clock),
        new RemoveAccountMemberships($this->members, $this->appointments, $clock, $this->transactions),
    );

    $this->leaveRefusal = function (string $accountId = StaffFixtures::ACCOUNT_ID): ?Throwable {
        try {
            $this->memberships->leaveTeamsNotOwned($accountId);
        } catch (Throwable $refusal) {
            return $refusal;
        }

        return null;
    };
});

it('implements the accounts port', function () {
    expect($this->memberships)->toBeInstanceOf(TeamMemberships::class);
});

describe('the owned business', function () {
    it('names the uuid of the business the account owns', function () {
        expect($this->memberships->ownedBusinessIdOf(StaffFixtures::ACCOUNT_ID))->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('still names a business the account owns once it is closed', function () {
        $this->members->closeBusiness(FakeBusinessContext::BUSINESS_ID);

        expect($this->memberships->ownedBusinessIdOf(StaffFixtures::ACCOUNT_ID))->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('names nothing for an account that only works for others', function () {
        expect($this->memberships->ownedBusinessIdOf(StaffFixtures::SECOND_ACCOUNT_ID))->toBeNull();
    });

    it('names nothing for an account with no memberships', function () {
        expect($this->memberships->ownedBusinessIdOf(StaffFixtures::THIRD_ACCOUNT_ID))->toBeNull();
    });
});

describe('upcoming appointments outside the owned business', function () {
    it('answers no when nothing is booked for the account', function () {
        expect($this->memberships->hasUpcomingAppointmentsOutsideOwnedBusiness(StaffFixtures::ACCOUNT_ID))->toBeFalse();
    });

    it('answers yes when a membership in an open business it does not own is booked', function () {
        $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

        expect($this->memberships->hasUpcomingAppointmentsOutsideOwnedBusiness(StaffFixtures::ACCOUNT_ID))->toBeTrue();
    });

    it('answers no when only the owned business is booked', function () {
        $this->appointments->busy(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

        expect($this->memberships->hasUpcomingAppointmentsOutsideOwnedBusiness(StaffFixtures::ACCOUNT_ID))->toBeFalse();
    });

    it('answers no when the booked membership sits in a closed business', function () {
        $this->members->closeBusiness(StaffFixtures::OTHER_BUSINESS_ID);
        $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

        expect($this->memberships->hasUpcomingAppointmentsOutsideOwnedBusiness(StaffFixtures::ACCOUNT_ID))->toBeFalse();
    });

    it('answers no when only a colleague on the same team is booked', function () {
        $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::THIRD_MEMBER_ID);

        expect($this->memberships->hasUpcomingAppointmentsOutsideOwnedBusiness(StaffFixtures::ACCOUNT_ID))->toBeFalse();
    });

    it('only reads', function () {
        $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

        $this->memberships->hasUpcomingAppointmentsOutsideOwnedBusiness(StaffFixtures::ACCOUNT_ID);

        expect($this->members->deleted)->toBe([])
            ->and($this->journal->entries)->toBe([]);
    });
});

describe('leaving the teams the account does not own', function () {
    it('removes every membership but the owned one', function () {
        $this->memberships->leaveTeamsNotOwned(StaffFixtures::ACCOUNT_ID);

        expect($this->members->deleted)->toBe([['businessId' => StaffFixtures::OTHER_BUSINESS_ID, 'id' => StaffFixtures::SECOND_MEMBER_ID]])
            ->and($this->members->stored(StaffFixtures::MEMBER_ID))->not->toBeNull()
            ->and($this->members->stored(StaffFixtures::THIRD_MEMBER_ID))->not->toBeNull();
    });

    it('declares nothing back on the port, so no use case response crosses it', function () {
        $returnType = (string) (new ReflectionMethod(TeamMemberships::class, 'leaveTeamsNotOwned'))->getReturnType();

        expect($returnType)->toBe('void')
            ->and($returnType)->not->toBe(UseCaseResponse::class);
    });

    it('throws the accounts refusal when a membership still has upcoming appointments', function () {
        $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

        $refusal = ($this->leaveRefusal)();

        expect($refusal)->toBeInstanceOf(AccountHasUpcomingAppointments::class)
            ->and($refusal->errorCode())->toBe('account_has_upcoming_appointments')
            ->and($refusal->kind())->toBe(DomainFailureKind::Conflict)
            ->and($refusal->getMessage())->toBe('Account ['.StaffFixtures::ACCOUNT_ID.'] still has upcoming appointments assigned in a business it does not own.');
    });

    it('removes nothing when it refuses', function () {
        $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

        ($this->leaveRefusal)();

        expect($this->members->deleted)->toBe([])
            ->and($this->journal->entries)->toBe([])
            ->and($this->members->stored(StaffFixtures::SECOND_MEMBER_ID))->not->toBeNull();
    });

    it('lets any other staff refusal out untranslated', function () {
        $vanished = StaffMemberNotFound::withId(StaffFixtures::SECOND_MEMBER_ID);
        $this->members->refuseDeleteWith($vanished);

        expect(($this->leaveRefusal)())->toBe($vanished);
    });
});
