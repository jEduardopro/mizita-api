<?php

declare(strict_types=1);

use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Staff\Infrastructure\Gateways\AccountsAccountDirectory;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use Tests\Support\Staff\StaffFixtures;

function anAccount(
    string $id = StaffFixtures::ACCOUNT_ID,
    string $name = 'Ada Lovelace',
    string $email = 'ada@example.com',
): Account {
    return Account::restore(
        id: $id,
        name: $name,
        email: $email,
        emailVerifiedAt: StaffFixtures::now(),
        createdAt: StaffFixtures::now(),
    );
}

beforeEach(function () {
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->directory = new AccountsAccountDirectory($this->accounts);
});

it('describes each account by the little the staff domain needs', function () {
    $this->accounts->shouldReceive('findManyByIds')->once()
        ->with([StaffFixtures::ACCOUNT_ID])
        ->andReturn([anAccount()]);

    $snapshots = $this->directory->describe([StaffFixtures::ACCOUNT_ID]);

    expect($snapshots)->toHaveCount(1)
        ->and($snapshots[0])->toBeInstanceOf(AccountSnapshot::class)
        ->and($snapshots[0]->id)->toBe(StaffFixtures::ACCOUNT_ID)
        ->and($snapshots[0]->name)->toBe('Ada Lovelace')
        ->and($snapshots[0]->email)->toBe('ada@example.com')
        ->and(array_keys(get_object_vars($snapshots[0])))->toBe(['id', 'name', 'email']);
});

it('keeps the order the repository handed the accounts over in', function () {
    $this->accounts->shouldReceive('findManyByIds')->once()->andReturn([
        anAccount(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com'),
        anAccount(),
    ]);

    expect(array_map(
        static fn (AccountSnapshot $account): string => $account->name,
        $this->directory->describe([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]),
    ))->toBe(['Grace Hopper', 'Ada Lovelace']);
});

it('passes the identifiers through untouched', function () {
    $this->accounts->shouldReceive('findManyByIds')->once()
        ->with([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID])
        ->andReturn([]);

    $this->directory->describe([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]);
});

it('describes nothing when the repository found nothing', function () {
    $this->accounts->shouldReceive('findManyByIds')->once()->with([])->andReturn([]);

    expect($this->directory->describe([]))->toBe([]);
});
