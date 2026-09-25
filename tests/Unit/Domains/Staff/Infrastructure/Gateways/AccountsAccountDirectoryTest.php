<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\UseCases\RenameAccount;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Exceptions\InvalidAccountName;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
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

function accountDirectoryRefusalFrom(callable $work): ?Throwable
{
    try {
        $work();
    } catch (Throwable $thrown) {
        return $thrown;
    }

    return null;
}

beforeEach(function () {
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->accounts->shouldReceive('idsAwaitingPasswordChange')->andReturn([])->byDefault();
    $this->directory = new AccountsAccountDirectory($this->accounts, new RenameAccount($this->accounts));
});

describe('describing accounts', function () {
    it('describes each account by the little the staff domain needs', function () {
        $this->accounts->shouldReceive('findManyByIds')->once()
            ->with([StaffFixtures::ACCOUNT_ID])
            ->andReturn([anAccount()]);
        $this->accounts->shouldReceive('idsHoldingPassword')->once()
            ->with([StaffFixtures::ACCOUNT_ID])
            ->andReturn([StaffFixtures::ACCOUNT_ID]);

        $snapshots = $this->directory->describe([StaffFixtures::ACCOUNT_ID]);

        expect($snapshots)->toHaveCount(1)
            ->and($snapshots[0])->toBeInstanceOf(AccountSnapshot::class)
            ->and($snapshots[0]->id)->toBe(StaffFixtures::ACCOUNT_ID)
            ->and($snapshots[0]->name)->toBe('Ada Lovelace')
            ->and($snapshots[0]->email)->toBe('ada@example.com')
            ->and($snapshots[0]->hasPassword)->toBeTrue()
            ->and($snapshots[0]->awaitingPasswordChange)->toBeFalse()
            ->and(array_keys(get_object_vars($snapshots[0])))->toBe(['id', 'name', 'email', 'hasPassword', 'awaitingPasswordChange']);
    });

    it('marks the account still holding the temporary password it was invited with', function () {
        $this->accounts->shouldReceive('findManyByIds')->once()->andReturn([
            anAccount(),
            anAccount(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com'),
        ]);
        $this->accounts->shouldReceive('idsHoldingPassword')->once()
            ->andReturn([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]);
        $this->accounts->shouldReceive('idsAwaitingPasswordChange')->once()
            ->with([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID])
            ->andReturn([StaffFixtures::SECOND_ACCOUNT_ID]);

        $snapshots = $this->directory->describe([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]);

        expect($snapshots[0]->awaitingPasswordChange)->toBeFalse()
            ->and($snapshots[1]->awaitingPasswordChange)->toBeTrue();
    });

    it('tells an account holding a password apart from one that signs in only through google', function () {
        $this->accounts->shouldReceive('findManyByIds')->once()->andReturn([
            anAccount(),
            anAccount(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com'),
        ]);
        $this->accounts->shouldReceive('idsHoldingPassword')->once()
            ->andReturn([StaffFixtures::SECOND_ACCOUNT_ID]);

        $snapshots = $this->directory->describe([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]);

        expect($snapshots[0]->hasPassword)->toBeFalse()
            ->and($snapshots[1]->hasPassword)->toBeTrue();
    });

    it('reports no password for anyone when no account holds one', function () {
        $this->accounts->shouldReceive('findManyByIds')->once()->andReturn([anAccount()]);
        $this->accounts->shouldReceive('idsHoldingPassword')->once()->andReturn([]);

        expect($this->directory->describe([StaffFixtures::ACCOUNT_ID])[0]->hasPassword)->toBeFalse();
    });

    it('asks about passwords once for the whole batch, never once per account', function () {
        $this->accounts->shouldReceive('findManyByIds')->once()->andReturn([
            anAccount(),
            anAccount(id: StaffFixtures::SECOND_ACCOUNT_ID),
        ]);
        $this->accounts->shouldReceive('idsHoldingPassword')->once()
            ->with([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID])
            ->andReturn([]);

        $this->directory->describe([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]);
    });

    it('keeps the order the repository handed the accounts over in', function () {
        $this->accounts->shouldReceive('findManyByIds')->once()->andReturn([
            anAccount(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com'),
            anAccount(),
        ]);
        $this->accounts->shouldReceive('idsHoldingPassword')->andReturn([]);

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

    it('describes nothing, and asks nothing about passwords, when the repository found nothing', function () {
        $this->accounts->shouldReceive('findManyByIds')->once()->with([])->andReturn([]);
        $this->accounts->shouldNotReceive('idsHoldingPassword');
        $this->accounts->shouldNotReceive('idsAwaitingPasswordChange');

        expect($this->directory->describe([]))->toBe([]);
    });
});

describe('renaming an account', function () {
    it('renames the account through the accounts use case and saves it trimmed', function () {
        $this->accounts->shouldReceive('findById')->once()
            ->with(StaffFixtures::ACCOUNT_ID)
            ->andReturn(anAccount());

        $saved = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $this->directory->rename(StaffFixtures::ACCOUNT_ID, '  Ada King  ');

        expect($saved)->toBeInstanceOf(Account::class)
            ->and($saved->id)->toBe(StaffFixtures::ACCOUNT_ID)
            ->and($saved->name())->toBe('Ada King')
            ->and($saved->email())->toBe('ada@example.com');
    });

    it('translates a missing account into the staff refusal, keeping the original as the cause', function () {
        $missing = AccountNotFound::withId(StaffFixtures::ACCOUNT_ID);
        $this->accounts->shouldReceive('findById')->once()->andThrow($missing);
        $this->accounts->shouldNotReceive('save');

        $thrown = accountDirectoryRefusalFrom(fn () => $this->directory->rename(StaffFixtures::ACCOUNT_ID, 'Ada King'));

        expect($thrown)->toBeInstanceOf(StaffMemberNotFound::class)
            ->and($thrown->errorCode())->toBe('staff_member_not_found')
            ->and($thrown->getPrevious())->toBe($missing);
    });

    it('translates a name the account refuses into the staff refusal, saving nothing', function (string $name) {
        $this->accounts->shouldReceive('findById')->once()->andReturn(anAccount());
        $this->accounts->shouldNotReceive('save');

        $thrown = accountDirectoryRefusalFrom(fn () => $this->directory->rename(StaffFixtures::ACCOUNT_ID, $name));

        expect($thrown)->toBeInstanceOf(InvalidProfileName::class)
            ->and($thrown->errorCode())->toBe('invalid_profile_name')
            ->and($thrown->getPrevious())->toBeInstanceOf(InvalidAccountName::class);
    })->with([
        'blank' => '   ',
        'too long' => str_repeat('a', Account::MAXIMUM_NAME_LENGTH + 1),
    ]);

    it('lets an infrastructure error out untranslated', function () {
        $bug = new RuntimeException('the users table is gone');
        $this->accounts->shouldReceive('findById')->once()->andThrow($bug);

        expect(accountDirectoryRefusalFrom(fn () => $this->directory->rename(StaffFixtures::ACCOUNT_ID, 'Ada King')))->toBe($bug);
    });
});
