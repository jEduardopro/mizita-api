<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\StaffMemberSummary;
use App\Domains\Staff\Application\UseCases\ListStaffMembers;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->members = Mockery::mock(StaffMemberRepository::class);
    $this->ada = StaffFixtures::account(name: 'Ada Lovelace', email: 'ada@example.com');
    $this->grace = StaffFixtures::account(
        id: StaffFixtures::SECOND_ACCOUNT_ID,
        name: 'Grace Hopper',
        email: 'grace@example.com',
    );

    $this->build = function (array $members, FakeAccountDirectory $accounts): ListStaffMembers {
        $this->members->shouldReceive('allForBusiness')
            ->with(FakeBusinessContext::BUSINESS_ID)
            ->andReturn($members)
            ->byDefault();

        $this->accounts = $accounts;

        return new ListStaffMembers($this->members, $accounts, new FakeBusinessContext);
    };
});

it('answers with a summary of every staff member of the business', function () {
    $useCase = ($this->build)(
        [
            StaffFixtures::member(role: StaffRole::Owner),
            StaffFixtures::member(
                id: StaffFixtures::SECOND_MEMBER_ID,
                accountId: StaffFixtures::SECOND_ACCOUNT_ID,
                role: StaffRole::Member,
            ),
        ],
        new FakeAccountDirectory($this->ada, $this->grace),
    );

    $summaries = $useCase->handle()->value();

    expect($summaries)->toHaveCount(2)
        ->and($summaries[0])->toBeInstanceOf(StaffMemberSummary::class)
        ->and($summaries[0]->id)->toBe(StaffFixtures::MEMBER_ID)
        ->and($summaries[0]->name)->toBe('Ada Lovelace')
        ->and($summaries[0]->email)->toBe('ada@example.com')
        ->and($summaries[0]->role)->toBe(StaffRole::Owner)
        ->and($summaries[1]->id)->toBe(StaffFixtures::SECOND_MEMBER_ID)
        ->and($summaries[1]->name)->toBe('Grace Hopper')
        ->and($summaries[1]->role)->toBe(StaffRole::Member);
});

it('reads the staff of the business in context and of no other', function () {
    $this->members->shouldReceive('allForBusiness')->once()
        ->with(FakeBusinessContext::BUSINESS_ID)
        ->andReturn([]);

    $useCase = new ListStaffMembers($this->members, new FakeAccountDirectory, new FakeBusinessContext);

    expect($useCase->handle()->succeeded())->toBeTrue();
});

it('keeps the order the repository listed the members in', function () {
    $useCase = ($this->build)(
        [
            StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID),
            StaffFixtures::member(),
        ],
        new FakeAccountDirectory($this->ada, $this->grace),
    );

    expect(array_map(
        static fn (StaffMemberSummary $summary): string => $summary->id,
        $useCase->handle()->value(),
    ))->toBe([StaffFixtures::SECOND_MEMBER_ID, StaffFixtures::MEMBER_ID]);
});

it('asks the accounts once, with each account named a single time', function () {
    $useCase = ($this->build)(
        [
            StaffFixtures::member(),
            StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID),
            StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID),
        ],
        new FakeAccountDirectory($this->ada, $this->grace),
    );

    $useCase->handle();

    expect($this->accounts->callCount())->toBe(1)
        ->and($this->accounts->lastCall())->toBe([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]);
});

it('names two memberships of one account by that same account', function () {
    $useCase = ($this->build)(
        [StaffFixtures::member(), StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID)],
        new FakeAccountDirectory($this->ada),
    );

    expect(array_map(
        static fn (StaffMemberSummary $summary): string => $summary->name,
        $useCase->handle()->value(),
    ))->toBe(['Ada Lovelace', 'Ada Lovelace']);
});

it('drops a member whose account the directory does not hand back, and keeps the rest', function () {
    $useCase = ($this->build)(
        [
            StaffFixtures::member(),
            StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID),
        ],
        new FakeAccountDirectory($this->ada),
    );

    $summaries = $useCase->handle()->value();

    expect($summaries)->toHaveCount(1)
        ->and($summaries[0]->id)->toBe(StaffFixtures::MEMBER_ID);
});

it('answers an empty business with an empty list, not a refusal', function () {
    $useCase = ($this->build)([], new FakeAccountDirectory);

    $response = $useCase->handle();

    expect($response->succeeded())->toBeTrue()
        ->and($response->value())->toBe([])
        ->and($this->accounts->lastCall())->toBe([]);
});
