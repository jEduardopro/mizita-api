<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\RemoveAccountMembershipsInput;
use App\Domains\Staff\Application\UseCases\RemoveAccountMemberships;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseError;
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
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, businessId: StaffFixtures::THIRD_BUSINESS_ID, role: StaffRole::NoAccess),
        StaffFixtures::member(id: StaffFixtures::FOURTH_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
    );
    $this->appointments = new FakeUpcomingAppointments;
    $this->clock = new FakeClock(new DateTimeImmutable('2026-10-25T00:30:00+00:00'));

    $this->remove = fn (string $accountId = StaffFixtures::ACCOUNT_ID): UseCaseResponse => (new RemoveAccountMemberships(
        $this->members,
        $this->appointments,
        $this->clock,
        $this->transactions,
    ))->handle(new RemoveAccountMembershipsInput($accountId));

    $this->refusal = function (string $accountId = StaffFixtures::ACCOUNT_ID): UseCaseError {
        $response = ($this->remove)($accountId);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };

    $this->nothingWasDeleted = function (): void {
        expect($this->members->deleted)->toBe([])
            ->and($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->members->stored(StaffFixtures::SECOND_MEMBER_ID))->not->toBeNull()
            ->and($this->members->stored(StaffFixtures::THIRD_MEMBER_ID))->not->toBeNull();
    };
});

describe('leaving every team the account does not own', function () {
    it('removes each membership outside the owned business and answers with no value', function () {
        $response = ($this->remove)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull()
            ->and($this->members->deleted)->toBe([
                ['businessId' => StaffFixtures::OTHER_BUSINESS_ID, 'id' => StaffFixtures::SECOND_MEMBER_ID],
                ['businessId' => StaffFixtures::THIRD_BUSINESS_ID, 'id' => StaffFixtures::THIRD_MEMBER_ID],
            ])
            ->and($this->members->stored(StaffFixtures::SECOND_MEMBER_ID))->toBeNull()
            ->and($this->members->stored(StaffFixtures::THIRD_MEMBER_ID))->toBeNull();
    });

    it('deletes every membership inside one transaction', function () {
        ($this->remove)()->value();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->journal->entries)->toBe(['members.delete', 'members.delete'])
            ->and($this->journal->outsideTransaction)->toBe([]);
    });

    it('looks the memberships up for that account, by its uuid', function () {
        ($this->remove)()->value();

        expect($this->members->membershipLookups)->toBe([
            ['scope' => 'open', 'accountId' => StaffFixtures::ACCOUNT_ID],
            ['scope' => 'all', 'accountId' => StaffFixtures::ACCOUNT_ID],
        ]);
    });

    it('never touches the membership of another account on the same team', function () {
        ($this->remove)()->value();

        expect($this->members->stored(StaffFixtures::FOURTH_MEMBER_ID))->not->toBeNull()
            ->and(array_column($this->members->deleted, 'id'))->not->toContain(StaffFixtures::FOURTH_MEMBER_ID);
    });

    it('succeeds without deleting anything for an account with no memberships at all', function () {
        $response = ($this->remove)(StaffFixtures::THIRD_ACCOUNT_ID);

        expect($response->succeeded())->toBeTrue()
            ->and($this->members->deleted)->toBe([])
            ->and($this->appointments->checks)->toBe([]);
    });
});

describe('the owner membership', function () {
    it('is never deleted, whether the owned business is open or closed', function (bool $closed) {
        if ($closed) {
            $this->members->closeBusiness(FakeBusinessContext::BUSINESS_ID);
        }

        ($this->remove)()->value();

        expect($this->members->stored(StaffFixtures::MEMBER_ID))->not->toBeNull()
            ->and(array_column($this->members->deleted, 'id'))->not->toContain(StaffFixtures::MEMBER_ID);
    })->with([
        'open' => false,
        'closed' => true,
    ]);

    it('never blocks with its own upcoming appointments, and is never asked about', function () {
        $this->appointments->busy(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

        expect(($this->remove)()->succeeded())->toBeTrue()
            ->and(array_column($this->appointments->checks, 'staffMemberId'))->not->toContain(StaffFixtures::MEMBER_ID)
            ->and($this->members->deleted)->toHaveCount(2);
    });

    it('leaves an account that only owns a business with every membership intact', function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::FOURTH_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Owner));

        expect(($this->remove)(StaffFixtures::SECOND_ACCOUNT_ID)->succeeded())->toBeTrue()
            ->and($this->members->deleted)->toBe([])
            ->and($this->members->stored(StaffFixtures::FOURTH_MEMBER_ID))->not->toBeNull();
    });
});

describe('upcoming appointments', function () {
    it('asks about every membership in an open business it does not own, as of the clock', function () {
        ($this->remove)()->value();

        expect($this->appointments->checks)->toEqual([
            ['businessId' => StaffFixtures::OTHER_BUSINESS_ID, 'staffMemberId' => StaffFixtures::SECOND_MEMBER_ID, 'now' => new DateTimeImmutable('2026-10-25T00:30:00+00:00')],
            ['businessId' => StaffFixtures::THIRD_BUSINESS_ID, 'staffMemberId' => StaffFixtures::THIRD_MEMBER_ID, 'now' => new DateTimeImmutable('2026-10-25T00:30:00+00:00')],
        ]);
    });

    it('asks as of the clock at the moment of removal, not a remembered instant', function () {
        $this->clock->advance('PT1H');

        ($this->remove)()->value();

        expect($this->appointments->checks[0]['now'])->toEqual(new DateTimeImmutable('2026-10-25T01:30:00+00:00'));
    });

    it('refuses the whole removal when any open membership still has one, deleting nothing', function (string $businessId, string $staffMemberId) {
        $this->appointments->busy($businessId, $staffMemberId);

        $error = ($this->refusal)();

        expect($error->code)->toBe('team_member_has_upcoming_appointments')
            ->and($error->kind)->toBe(DomainFailureKind::Conflict)
            ->and($error->cause()?->getMessage())->toBe("Staff member [{$staffMemberId}] still has upcoming appointments.");

        ($this->nothingWasDeleted)();
    })->with([
        'the first membership' => [StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID],
        'the last membership' => [StaffFixtures::THIRD_BUSINESS_ID, StaffFixtures::THIRD_MEMBER_ID],
    ]);

    it('never lets a membership in a closed business block, and still removes it', function () {
        $this->members->closeBusiness(StaffFixtures::THIRD_BUSINESS_ID);
        $this->appointments->busy(StaffFixtures::THIRD_BUSINESS_ID, StaffFixtures::THIRD_MEMBER_ID);

        $response = ($this->remove)();

        expect($response->succeeded())->toBeTrue()
            ->and(array_column($this->appointments->checks, 'staffMemberId'))->toBe([StaffFixtures::SECOND_MEMBER_ID])
            ->and($this->members->deleted)->toBe([
                ['businessId' => StaffFixtures::OTHER_BUSINESS_ID, 'id' => StaffFixtures::SECOND_MEMBER_ID],
                ['businessId' => StaffFixtures::THIRD_BUSINESS_ID, 'id' => StaffFixtures::THIRD_MEMBER_ID],
            ]);
    });

    it('removes every membership when all of them sit in closed businesses', function () {
        $this->members->closeBusiness(StaffFixtures::OTHER_BUSINESS_ID)->closeBusiness(StaffFixtures::THIRD_BUSINESS_ID);
        $this->appointments
            ->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID)
            ->busy(StaffFixtures::THIRD_BUSINESS_ID, StaffFixtures::THIRD_MEMBER_ID);

        expect(($this->remove)()->succeeded())->toBeTrue()
            ->and($this->appointments->checks)->toBe([])
            ->and($this->members->deleted)->toHaveCount(2);
    });

    it('is not blocked by the appointments of another account on the same team', function () {
        $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::FOURTH_MEMBER_ID);

        expect(($this->remove)()->succeeded())->toBeTrue()
            ->and($this->members->deleted)->toHaveCount(2);
    });
});

it('answers a refusal raised by a delete inside the transaction as a failure', function () {
    $vanished = StaffMemberNotFound::withId(StaffFixtures::SECOND_MEMBER_ID);
    $this->members->refuseDeleteWith($vanished);

    $error = ($this->refusal)();

    expect($error->code)->toBe('staff_member_not_found')
        ->and($error->kind)->toBe(DomainFailureKind::NotFound)
        ->and($error->cause())->toBe($vanished)
        ->and($this->journal->outsideTransaction)->toBe([])
        ->and($this->members->deleted)->toBe([]);
});
