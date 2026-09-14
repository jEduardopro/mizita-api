<?php

declare(strict_types=1);

use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Infrastructure\Eloquent\Mappers\AccountMapper;

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
    $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);

    expect($this->mapper->toAttributes($account))->not->toHaveKey('password');
});

it('never writes the internal primary key', function () {
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
