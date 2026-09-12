<?php

declare(strict_types=1);

use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Infrastructure\Eloquent\Mappers\AccountMapper;

/*
| Only the entity -> attributes direction is a unit test. Going the other way
| reads $model->email_verified_at, and casting that attribute asks the model for
| its connection's date format - so toEntity() needs a real connection and
| belongs in a feature test, not in a stubbed-out imitation of one here.
*/

beforeEach(function () {
    $this->mapper = new AccountMapper;
});

it('writes the uuid, the name, the email and the verification timestamp', function () {
    $account = Account::restore(
        'account-uuid',
        'Ada Lovelace',
        'ada@example.com',
        new DateTimeImmutable('2026-01-01 12:00:00'),
        new DateTimeImmutable('2025-05-01 08:30:00'),
    );

    expect($this->mapper->toAttributes($account))->toBe([
        'uuid' => 'account-uuid',
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'email_verified_at' => $account->emailVerifiedAt(),
    ]);
});

it('never writes a password', function () {
    // Linking Google to an account that already has a password must not touch
    // that password. The entity carries no hash, and the attribute is absent
    // rather than null, because null would overwrite it.
    $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);

    expect($this->mapper->toAttributes($account))->not->toHaveKey('password');
});

it('never writes the internal primary key', function () {
    // uuid public, int internal: the int is an infrastructure detail the
    // mapper has no business inventing or carrying.
    $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);

    expect($this->mapper->toAttributes($account))->not->toHaveKey('id');
});

it('writes no attribute the Account entity does not hold', function () {
    $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);

    expect(array_keys($this->mapper->toAttributes($account)))
        ->toBe(['uuid', 'name', 'email', 'email_verified_at']);
});

it('leaves the verification timestamp null for an unverified account', function () {
    $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);

    expect($this->mapper->toAttributes($account)['email_verified_at'])->toBeNull();
});

it('preserves accents and case exactly as the entity holds them', function () {
    $account = Account::restore('account-uuid', 'José Álvarez', 'jose@example.com', null, new DateTimeImmutable);

    expect($this->mapper->toAttributes($account)['name'])->toBe('José Álvarez');
});
