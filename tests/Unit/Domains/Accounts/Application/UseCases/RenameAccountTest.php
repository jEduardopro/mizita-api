<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\RenameAccountInput;
use App\Domains\Accounts\Application\UseCases\RenameAccount;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Shared\ValueObjects\DomainFailureKind;

const RENAMED_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a1';

function accountToRename(): Account
{
    return Account::restore(
        id: RENAMED_ACCOUNT_ID,
        name: 'Ada Lovelace',
        email: 'ada@example.com',
        emailVerifiedAt: new DateTimeImmutable('2025-06-01T08:30:00+00:00'),
        createdAt: new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
    );
}

beforeEach(function () {
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->useCase = new RenameAccount($this->accounts);
});

it('renames the account and saves it', function () {
    $this->accounts->shouldReceive('findById')->once()->with(RENAMED_ACCOUNT_ID)->andReturn(accountToRename());

    $saved = null;
    $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($saved));

    $response = $this->useCase->handle(new RenameAccountInput(RENAMED_ACCOUNT_ID, '  Ada King  '));

    expect($response->succeeded())->toBeTrue()
        ->and($response->value())->toBeNull()
        ->and($response->warnings())->toBe([])
        ->and($saved)->toBeInstanceOf(Account::class)
        ->and($saved->id)->toBe(RENAMED_ACCOUNT_ID)
        ->and($saved->name())->toBe('Ada King')
        ->and($saved->email())->toBe('ada@example.com');
});

it('answers with not found, saving nothing, when the account does not exist', function () {
    $missing = AccountNotFound::withId(RENAMED_ACCOUNT_ID);
    $this->accounts->shouldReceive('findById')->once()->andThrow($missing);
    $this->accounts->shouldNotReceive('save');

    $response = $this->useCase->handle(new RenameAccountInput(RENAMED_ACCOUNT_ID, 'Ada King'));

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('account_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($response->error()->cause())->toBe($missing);
});

it('answers with an invalid name, saving nothing, when the account refuses it', function (string $name) {
    $this->accounts->shouldReceive('findById')->once()->andReturn(accountToRename());
    $this->accounts->shouldNotReceive('save');

    $response = $this->useCase->handle(new RenameAccountInput(RENAMED_ACCOUNT_ID, $name));

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('invalid_account_name')
        ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
})->with([
    'empty' => '',
    'whitespace only' => " \t ",
    'one past the limit' => str_repeat('a', Account::MAXIMUM_NAME_LENGTH + 1),
]);

it('lets a programmer error escape rather than dressing it as a domain failure', function () {
    $bug = new RuntimeException('the users table is gone');
    $this->accounts->shouldReceive('findById')->once()->andReturn(accountToRename());
    $this->accounts->shouldReceive('save')->once()->andThrow($bug);

    expect(fn () => $this->useCase->handle(new RenameAccountInput(RENAMED_ACCOUNT_ID, 'Ada King')))
        ->toThrow($bug);
});
