<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AuthenticatedAccountData;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;

function authenticatedAccountWith(TwoFactorStatus $status): Account
{
    return Account::restore(
        '01930000-0000-7000-8000-00000000ad01',
        'José Álvarez',
        'jose@example.com',
        new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
        new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
        twoFactorStatus: $status,
    );
}

describe('an existing account', function () {
    it('carries the account uuid, name and email, and is not new', function () {
        $data = AuthenticatedAccountData::forExistingAccount(authenticatedAccountWith(TwoFactorStatus::Disabled));

        expect($data->id)->toBe('01930000-0000-7000-8000-00000000ad01')
            ->and($data->name)->toBe('José Álvarez')
            ->and($data->email)->toBe('jose@example.com')
            ->and($data->isNewAccount)->toBeFalse();
    });

    it('owes a second factor only once two factor authentication is confirmed', function (TwoFactorStatus $status, bool $requiresSecondFactor) {
        expect(AuthenticatedAccountData::forExistingAccount(authenticatedAccountWith($status))->requiresSecondFactor)
            ->toBe($requiresSecondFactor);
    })->with([
        'enabled' => [TwoFactorStatus::Enabled, true],
        'set up but never confirmed' => [TwoFactorStatus::Pending, false],
        'disabled' => [TwoFactorStatus::Disabled, false],
    ]);
});

describe('a freshly registered account', function () {
    it('carries the account uuid, name and email, is new, and owes no second factor', function () {
        $data = AuthenticatedAccountData::forNewAccount(Account::registerWithVerifiedEmail(
            id: '01930000-0000-7000-8000-00000000ad02',
            name: '  Grace Hopper ',
            email: 'Grace@Example.com',
            now: new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
        ));

        expect($data->id)->toBe('01930000-0000-7000-8000-00000000ad02')
            ->and($data->name)->toBe('Grace Hopper')
            ->and($data->email)->toBe('grace@example.com')
            ->and($data->isNewAccount)->toBeTrue()
            ->and($data->requiresSecondFactor)->toBeFalse();
    });
});
