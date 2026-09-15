<?php

declare(strict_types=1);

use App\Domains\Services\Infrastructure\Gateways\StaffStaffDirectory;
use App\Domains\Services\ValueObjects\StaffMemberSnapshot;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Services\ServiceFixtures;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->members = Mockery::mock(StaffMemberRepository::class);

    $this->build = function (array $members, FakeAccountDirectory $accounts): StaffStaffDirectory {
        $this->members->shouldReceive('allForBusiness')
            ->with(FakeBusinessContext::BUSINESS_ID)
            ->andReturn($members)
            ->byDefault();

        $this->accounts = $accounts;

        return new StaffStaffDirectory($this->members, $accounts);
    };

    $this->ada = StaffFixtures::account(name: 'Ada Lovelace');
    $this->grace = StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper');
});

it('describes the staff it was asked about by name', function () {
    $directory = ($this->build)(
        [
            StaffFixtures::member(),
            StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID),
        ],
        new FakeAccountDirectory($this->ada, $this->grace),
    );

    $snapshots = $directory->membersOf(FakeBusinessContext::BUSINESS_ID, [
        StaffFixtures::MEMBER_ID,
        StaffFixtures::SECOND_MEMBER_ID,
    ]);

    expect($snapshots)->toHaveCount(2)
        ->and($snapshots[0])->toBeInstanceOf(StaffMemberSnapshot::class)
        ->and($snapshots[0]->id)->toBe(StaffFixtures::MEMBER_ID)
        ->and($snapshots[0]->name)->toBe('Ada Lovelace')
        ->and($snapshots[1]->name)->toBe('Grace Hopper');
});

it('carries no email into a snapshot the services domain will render', function () {
    $directory = ($this->build)([StaffFixtures::member()], new FakeAccountDirectory($this->ada));

    $snapshot = $directory->membersOf(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::MEMBER_ID])[0];

    expect(array_keys(get_object_vars($snapshot)))->toBe(['id', 'name']);
});

it('leaves out a staff member the business does not have', function () {
    $directory = ($this->build)([StaffFixtures::member()], new FakeAccountDirectory($this->ada));

    $snapshots = $directory->membersOf(FakeBusinessContext::BUSINESS_ID, [
        StaffFixtures::MEMBER_ID,
        ServiceFixtures::FOREIGN_STAFF_ID,
    ]);

    expect($snapshots)->toHaveCount(1)
        ->and($snapshots[0]->id)->toBe(StaffFixtures::MEMBER_ID);
});

it('leaves out a staff member whose account the directory does not know', function () {
    $directory = ($this->build)(
        [
            StaffFixtures::member(),
            StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID),
        ],
        new FakeAccountDirectory($this->ada),
    );

    $snapshots = $directory->membersOf(FakeBusinessContext::BUSINESS_ID, [
        StaffFixtures::MEMBER_ID,
        StaffFixtures::SECOND_MEMBER_ID,
    ]);

    expect($snapshots)->toHaveCount(1)
        ->and($snapshots[0]->id)->toBe(StaffFixtures::MEMBER_ID);
});

it('asks the accounts once, with each account named a single time', function () {
    $directory = ($this->build)(
        [
            StaffFixtures::member(),
            StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID),
            StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID),
        ],
        new FakeAccountDirectory($this->ada, $this->grace),
    );

    $directory->membersOf(FakeBusinessContext::BUSINESS_ID, [
        StaffFixtures::MEMBER_ID,
        StaffFixtures::SECOND_MEMBER_ID,
        StaffFixtures::THIRD_MEMBER_ID,
    ]);

    expect($this->accounts->callCount())->toBe(1)
        ->and($this->accounts->lastCall())->toBe([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]);
});

it('asks nobody anything when no staff was selected', function () {
    $this->members->shouldNotReceive('allForBusiness');
    $accounts = new FakeAccountDirectory($this->ada);

    $directory = new StaffStaffDirectory($this->members, $accounts);

    expect($directory->membersOf(FakeBusinessContext::BUSINESS_ID, []))->toBe([])
        ->and($accounts->callCount())->toBe(0);
});

it('asks the accounts nothing when none of the selected staff is of that business', function () {
    $directory = ($this->build)([StaffFixtures::member()], new FakeAccountDirectory($this->ada));

    expect($directory->membersOf(FakeBusinessContext::BUSINESS_ID, [ServiceFixtures::FOREIGN_STAFF_ID]))->toBe([])
        ->and($this->accounts->callCount())->toBe(0);
});

it('reads the staff of the business it was asked about', function () {
    $this->members->shouldReceive('allForBusiness')->once()
        ->with(ServiceFixtures::OTHER_BUSINESS_ID)
        ->andReturn([]);

    $directory = new StaffStaffDirectory($this->members, new FakeAccountDirectory($this->ada));

    expect($directory->membersOf(ServiceFixtures::OTHER_BUSINESS_ID, [StaffFixtures::MEMBER_ID]))->toBe([]);
});
