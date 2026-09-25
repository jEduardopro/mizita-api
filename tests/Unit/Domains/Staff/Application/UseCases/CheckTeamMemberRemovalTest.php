<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\CheckTeamMemberRemovalInput;
use App\Domains\Staff\Application\Dtos\TeamMemberRemovalData;
use App\Domains\Staff\Application\UseCases\CheckTeamMemberRemoval;
use App\Domains\Staff\ValueObjects\RemovalBlocker;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeUpcomingAppointments;
use Tests\Support\Staff\StaffFixtures;
use Tests\Support\Staff\StaffJournal;

beforeEach(function () {
    $this->journal = new StaffJournal;
    $this->members = (new FakeStaffMemberRepository($this->journal))->store(
        StaffFixtures::member(),
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::THIRD_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
    );
    $this->appointments = new FakeUpcomingAppointments;
    $this->clock = new FakeClock(new DateTimeImmutable('2026-10-25T00:30:00+00:00'));

    $this->build = fn (?FakeBusinessContext $business = null): CheckTeamMemberRemoval => new CheckTeamMemberRemoval(
        $this->members,
        $this->appointments,
        $business ?? new FakeBusinessContext,
        $this->clock,
    );

    $this->check = fn (string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID, ?CheckTeamMemberRemoval $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(new CheckTeamMemberRemovalInput($staffMemberId));

    $this->removal = fn (string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID, ?CheckTeamMemberRemoval $useCase = null): TeamMemberRemovalData => ($this->check)($staffMemberId, $useCase)->value();

    $this->refusal = function (string $staffMemberId): UseCaseError {
        $response = ($this->check)($staffMemberId);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };

    $this->nothingWasWritten = function (): void {
        expect($this->members->saved)->toBe([])
            ->and($this->members->deleted)->toBe([])
            ->and($this->journal->entries)->toBe([]);
    };
});

it('answers that a member with nothing in the way is removable', function () {
    $removal = ($this->removal)();

    expect($removal)->toBeInstanceOf(TeamMemberRemovalData::class)
        ->and($removal->removable())->toBeTrue()
        ->and($removal->blocker)->toBeNull();
});

it('answers that a member who was never given access is removable', function () {
    $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::NoAccess));

    expect(($this->removal)()->blocker)->toBeNull();
});

it('names ownership as the blocker for the owner without asking about appointments', function () {
    $this->appointments->busy(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

    $removal = ($this->removal)(StaffFixtures::MEMBER_ID);

    expect($removal->removable())->toBeFalse()
        ->and($removal->blocker)->toBe(RemovalBlocker::Owner)
        ->and($this->appointments->checks)->toBe([]);
});

it('names upcoming appointments as the blocker for a member who still has them', function () {
    $this->appointments->busy(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

    $removal = ($this->removal)();

    expect($removal->removable())->toBeFalse()
        ->and($removal->blocker)->toBe(RemovalBlocker::UpcomingAppointments);
});

it('asks about upcoming appointments once, for that member, in its business, as of the clock', function () {
    ($this->removal)();

    expect($this->appointments->checks)->toHaveCount(1)
        ->and($this->appointments->checks[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($this->appointments->checks[0]['staffMemberId'])->toBe(StaffFixtures::SECOND_MEMBER_ID)
        ->and($this->appointments->checks[0]['now'])->toEqual(new DateTimeImmutable('2026-10-25T00:30:00+00:00'));
});

it('asks as of the clock at the moment of the check, not a remembered instant', function () {
    $this->clock->advance('PT1H');

    ($this->removal)();

    expect($this->appointments->checks[0]['now'])->toEqual(new DateTimeImmutable('2026-10-25T01:30:00+00:00'));
});

it('answers removable for a member whose appointments are all at another business', function () {
    $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

    expect(($this->removal)()->removable())->toBeTrue();
});

it('only reads, whatever the answer', function (string $staffMemberId) {
    $this->appointments->busy(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

    ($this->check)($staffMemberId);

    expect($this->members->stored(StaffFixtures::MEMBER_ID))->not->toBeNull()
        ->and($this->members->stored(StaffFixtures::SECOND_MEMBER_ID))->not->toBeNull();

    ($this->nothingWasWritten)();
})->with([
    'the owner' => StaffFixtures::MEMBER_ID,
    'a busy member' => StaffFixtures::SECOND_MEMBER_ID,
    'a member of another business' => StaffFixtures::THIRD_MEMBER_ID,
    'a malformed id' => '42',
]);

it('refuses a value that is not a uuid without looking anything up', function (string $staffMemberId) {
    $error = ($this->refusal)($staffMemberId);

    expect($error->code)->toBe('staff_member_not_found')
        ->and($error->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->members->businessLookups)->toBe([])
        ->and($this->appointments->checks)->toBe([]);
})->with([
    'empty' => '',
    'an int id' => '42',
    'garbage' => 'not-a-uuid',
]);

it('refuses a member nobody has without asking about appointments', function () {
    $error = ($this->refusal)('01930000-0000-7000-8000-0000000000d9');

    expect($error->code)->toBe('staff_member_not_found')
        ->and($error->kind)->toBe(DomainFailureKind::NotFound)
        ->and($this->appointments->checks)->toBe([]);
});

describe('tenant isolation', function () {
    it('refuses a member of another business, looking it up only in the business of the context', function () {
        $error = ($this->refusal)(StaffFixtures::THIRD_MEMBER_ID);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->members->businessLookups)->toBe([['businessId' => FakeBusinessContext::BUSINESS_ID, 'id' => StaffFixtures::THIRD_MEMBER_ID]])
            ->and($this->appointments->checks)->toBe([]);
    });

    it('checks that member in its own business when that business is the one in context', function () {
        $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::THIRD_MEMBER_ID);

        $removal = ($this->removal)(StaffFixtures::THIRD_MEMBER_ID, ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)));

        expect($removal->blocker)->toBe(RemovalBlocker::UpcomingAppointments)
            ->and($this->members->businessLookups)->toBe([['businessId' => StaffFixtures::OTHER_BUSINESS_ID, 'id' => StaffFixtures::THIRD_MEMBER_ID]])
            ->and($this->appointments->checks[0]['businessId'])->toBe(StaffFixtures::OTHER_BUSINESS_ID)
            ->and($this->appointments->checks[0]['staffMemberId'])->toBe(StaffFixtures::THIRD_MEMBER_ID);
    });
});
