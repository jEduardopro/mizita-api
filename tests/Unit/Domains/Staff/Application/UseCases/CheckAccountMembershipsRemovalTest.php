<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\CheckAccountMembershipsRemovalInput;
use App\Domains\Staff\Application\Dtos\TeamMemberRemovalData;
use App\Domains\Staff\Application\UseCases\CheckAccountMembershipsRemoval;
use App\Domains\Staff\ValueObjects\RemovalBlocker;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeUpcomingAppointments;
use Tests\Support\Staff\StaffFixtures;
use Tests\Support\Staff\StaffJournal;

beforeEach(function () {
    $this->journal = new StaffJournal;
    $this->members = (new FakeStaffMemberRepository($this->journal))->store(
        StaffFixtures::member(id: StaffFixtures::MEMBER_ID, businessId: FakeBusinessContext::BUSINESS_ID, role: StaffRole::Owner),
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, businessId: StaffFixtures::THIRD_BUSINESS_ID, role: StaffRole::NoAccess),
        StaffFixtures::member(id: StaffFixtures::FOURTH_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
    );
    $this->appointments = new FakeUpcomingAppointments;
    $this->clock = new FakeClock(new DateTimeImmutable('2026-03-29T00:30:00+00:00'));

    $this->removal = fn (string $accountId = StaffFixtures::ACCOUNT_ID): TeamMemberRemovalData => (new CheckAccountMembershipsRemoval(
        $this->members,
        $this->appointments,
        $this->clock,
    ))->handle(new CheckAccountMembershipsRemovalInput($accountId))->value();
});

it('answers removable when no membership has anything ahead', function () {
    $removal = ($this->removal)();

    expect($removal)->toBeInstanceOf(TeamMemberRemovalData::class)
        ->and($removal->blocker)->toBeNull()
        ->and($removal->removable())->toBeTrue();
});

it('answers removable for an account with no memberships at all', function () {
    expect(($this->removal)(StaffFixtures::THIRD_ACCOUNT_ID)->blocker)->toBeNull()
        ->and($this->appointments->checks)->toBe([]);
});

it('names upcoming appointments when any open membership it does not own has one', function (string $businessId, string $staffMemberId) {
    $this->appointments->busy($businessId, $staffMemberId);

    $removal = ($this->removal)();

    expect($removal->blocker)->toBe(RemovalBlocker::UpcomingAppointments)
        ->and($removal->removable())->toBeFalse();
})->with([
    'a staff membership' => [StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID],
    'a no access membership' => [StaffFixtures::THIRD_BUSINESS_ID, StaffFixtures::THIRD_MEMBER_ID],
]);

it('ignores the appointments of the owned business without asking about them', function () {
    $this->appointments->busy(FakeBusinessContext::BUSINESS_ID, StaffFixtures::MEMBER_ID);

    expect(($this->removal)()->blocker)->toBeNull()
        ->and(array_column($this->appointments->checks, 'staffMemberId'))->not->toContain(StaffFixtures::MEMBER_ID);
});

it('never names ownership as the blocker, since the owner membership is not left', function () {
    expect(($this->removal)()->blocker)->not->toBe(RemovalBlocker::Owner);
});

it('ignores a membership in a closed business without asking about it', function () {
    $this->members->closeBusiness(StaffFixtures::OTHER_BUSINESS_ID);
    $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

    expect(($this->removal)()->blocker)->toBeNull()
        ->and(array_column($this->appointments->checks, 'staffMemberId'))->toBe([StaffFixtures::THIRD_MEMBER_ID]);
});

it('looks only at the open memberships of that account, by its uuid', function () {
    ($this->removal)();

    expect($this->members->membershipLookups)->toBe([['scope' => 'open', 'accountId' => StaffFixtures::ACCOUNT_ID]]);
});

it('is not blocked by the appointments of another account on the same team', function () {
    $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::FOURTH_MEMBER_ID);

    expect(($this->removal)()->blocker)->toBeNull();
});

it('asks each membership in its own business, as of the clock', function () {
    ($this->removal)();

    expect($this->appointments->checks)->toEqual([
        ['businessId' => StaffFixtures::OTHER_BUSINESS_ID, 'staffMemberId' => StaffFixtures::SECOND_MEMBER_ID, 'now' => new DateTimeImmutable('2026-03-29T00:30:00+00:00')],
        ['businessId' => StaffFixtures::THIRD_BUSINESS_ID, 'staffMemberId' => StaffFixtures::THIRD_MEMBER_ID, 'now' => new DateTimeImmutable('2026-03-29T00:30:00+00:00')],
    ]);
});

it('asks as of the clock at the moment of the check, not a remembered instant', function () {
    $this->clock->advance('PT1H');

    ($this->removal)();

    expect($this->appointments->checks[0]['now'])->toEqual(new DateTimeImmutable('2026-03-29T01:30:00+00:00'));
});

it('only reads, whatever the answer', function () {
    $this->appointments->busy(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::SECOND_MEMBER_ID);

    ($this->removal)();

    expect($this->members->saved)->toBe([])
        ->and($this->members->deleted)->toBe([])
        ->and($this->journal->entries)->toBe([])
        ->and($this->members->stored(StaffFixtures::SECOND_MEMBER_ID))->not->toBeNull();
});
