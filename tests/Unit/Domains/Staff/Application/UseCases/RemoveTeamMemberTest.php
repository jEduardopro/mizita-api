<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\RemoveTeamMemberInput;
use App\Domains\Staff\Application\UseCases\RemoveTeamMember;
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
        StaffFixtures::member(),
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::THIRD_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
    );
    $this->appointments = new FakeUpcomingAppointments;
    $this->clock = new FakeClock(new DateTimeImmutable('2026-10-25T00:30:00+00:00'));

    $this->build = fn (?FakeBusinessContext $business = null): RemoveTeamMember => new RemoveTeamMember(
        $this->members,
        $this->appointments,
        $business ?? new FakeBusinessContext,
        $this->clock,
        $this->transactions,
    );

    $this->remove = fn (string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID, ?RemoveTeamMember $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(new RemoveTeamMemberInput($staffMemberId));

    $this->refusal = function (string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID): UseCaseError {
        $response = ($this->remove)($staffMemberId);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };

    $this->nothingWasDeleted = function (): void {
        expect($this->members->deleted)->toBe([])
            ->and($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    };
});

it('removes the member from the business in context, inside a transaction', function () {
    $response = ($this->remove)();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value())->toBeNull()
        ->and($this->members->deleted)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'id' => StaffFixtures::SECOND_MEMBER_ID]])
        ->and($this->members->stored(StaffFixtures::SECOND_MEMBER_ID))->toBeNull()
        ->and($this->journal->outsideTransaction)->toBe([])
        ->and($this->transactions->runs())->toBe(1);
});

it('removes a member who was never given access', function () {
    $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::NoAccess));

    expect(($this->remove)()->succeeded())->toBeTrue()
        ->and($this->members->deleted)->toHaveCount(1);
});

it('asks about upcoming appointments for that member, in its business, as of the clock', function () {
    ($this->remove)();

    expect($this->appointments->checks)->toHaveCount(1)
        ->and($this->appointments->checks[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($this->appointments->checks[0]['staffMemberId'])->toBe(StaffFixtures::SECOND_MEMBER_ID)
        ->and($this->appointments->checks[0]['now'])->toEqual(new DateTimeImmutable('2026-10-25T00:30:00+00:00'));
});

it('refuses to remove the owner without asking about appointments', function () {
    $error = ($this->refusal)(StaffFixtures::MEMBER_ID);

    expect($error->code)->toBe('owner_cannot_be_removed')
        ->and($error->kind)->toBe(DomainFailureKind::Conflict)
        ->and($this->appointments->checks)->toBe([]);

    ($this->nothingWasDeleted)();
});

it('refuses to remove a member who still has upcoming appointments', function () {
    $this->appointments->busy(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

    $error = ($this->refusal)();

    expect($error->code)->toBe('team_member_has_upcoming_appointments')
        ->and($error->kind)->toBe(DomainFailureKind::Conflict)
        ->and($error->cause()?->getMessage())->toBe('Staff member ['.StaffFixtures::SECOND_MEMBER_ID.'] still has upcoming appointments.');

    ($this->nothingWasDeleted)();
});

it('removes a member whose appointments are all at another business', function () {
    $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

    expect(($this->remove)()->succeeded())->toBeTrue();
});

it('refuses a value that is not a uuid without looking anything up', function () {
    $error = ($this->refusal)('42');

    expect($error->code)->toBe('staff_member_not_found')
        ->and($error->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->members->businessLookups)->toBe([]);

    ($this->nothingWasDeleted)();
});

it('refuses a member nobody has', function () {
    expect(($this->refusal)('01930000-0000-7000-8000-0000000000d9')->code)->toBe('staff_member_not_found');

    ($this->nothingWasDeleted)();
});

it('answers a refusal raised by the delete inside the transaction as a failure', function () {
    $vanished = StaffMemberNotFound::withId(StaffFixtures::SECOND_MEMBER_ID);
    $this->members->refuseDeleteWith($vanished);

    $error = ($this->refusal)();

    expect($error->code)->toBe('staff_member_not_found')
        ->and($error->cause())->toBe($vanished)
        ->and($this->journal->entries)->toBe(['members.delete'])
        ->and($this->journal->outsideTransaction)->toBe([])
        ->and($this->members->deleted)->toBe([]);
});

describe('tenant isolation', function () {
    it('refuses a member of another business, looking it up only in the business of the context', function () {
        $error = ($this->refusal)(StaffFixtures::THIRD_MEMBER_ID);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($this->members->businessLookups)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'id' => StaffFixtures::THIRD_MEMBER_ID]])
            ->and($this->members->stored(StaffFixtures::THIRD_MEMBER_ID))->not->toBeNull();

        ($this->nothingWasDeleted)();
    });

    it('removes that member when its business is the one in context', function () {
        ($this->remove)(StaffFixtures::THIRD_MEMBER_ID, ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)))->value();

        expect($this->members->deleted)->toBe([['businessId' => StaffFixtures::OTHER_BUSINESS_ID, 'id' => StaffFixtures::THIRD_MEMBER_ID]])
            ->and($this->appointments->checks[0]['businessId'])->toBe(StaffFixtures::OTHER_BUSINESS_ID);
    });
});
