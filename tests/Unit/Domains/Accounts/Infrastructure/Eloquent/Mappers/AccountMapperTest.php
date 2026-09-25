<?php

declare(strict_types=1);

use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Infrastructure\Eloquent\Mappers\AccountMapper;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;
use App\Models\User;

/**
 * @param  array<string, mixed>  $attributes
 */
function storedAccountUser(array $attributes): User
{
    return (new User)->setRawAttributes([
        'id' => 7,
        'uuid' => '01930000-0000-7000-8000-0000000000c1',
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'email_verified_at' => null,
        'created_at' => new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
        ...$attributes,
    ], sync: true);
}

/**
 * @param  array<string, mixed>  $attributes
 * @return list<string>
 */
function twoFactorKeysIn(array $attributes): array
{
    return array_values(array_filter(
        array_keys($attributes),
        static fn (string $key): bool => str_starts_with($key, 'two_factor'),
    ));
}

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
        'deleted_at' => null,
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
        ->toBe(['uuid', 'name', 'email', 'email_verified_at', 'deleted_at']);
});

it('leaves the verification timestamp null for an unverified account', function () {
    $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);

    expect($this->mapper->toAttributes($account)['email_verified_at'])->toBeNull();
});

describe('an issued temporary password', function () {
    it('writes the issued hash and raises the change flag', function () {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);
        $account->issueTemporaryPassword('temporary-hash');

        expect($this->mapper->toAttributes($account))->toBe([
            'uuid' => 'account-uuid',
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'email_verified_at' => null,
            'deleted_at' => null,
            'password' => 'temporary-hash',
            'must_change_password' => true,
        ]);
    });

    it('writes the hash of a freshly invited account', function () {
        $account = Account::inviteWithTemporaryPassword('account-uuid', 'Ada', 'ada@example.com', 'temporary-hash', new DateTimeImmutable);

        $attributes = $this->mapper->toAttributes($account);

        expect($attributes['password'])->toBe('temporary-hash')
            ->and($attributes['must_change_password'])->toBeTrue();
    });

    it('leaves the password and the flag alone for an account invited without one', function () {
        $account = Account::inviteWithoutPassword('account-uuid', 'Ada', 'ada@example.com', new DateTimeImmutable);

        expect($this->mapper->toAttributes($account))->not->toHaveKey('password')
            ->not->toHaveKey('must_change_password');
    });

    it('leaves the stored password and flag alone on a restored account, whatever its status', function (PasswordStatus $status) {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable, $status);

        expect($this->mapper->toAttributes($account))->not->toHaveKey('password')
            ->not->toHaveKey('must_change_password');
    })->with([PasswordStatus::Absent, PasswordStatus::Temporary, PasswordStatus::Chosen]);
});

describe('reading the password status off a stored user', function () {
    it('restores the status the password columns describe', function (?string $password, ?bool $mustChangePassword, bool $expectMustChange, bool $expectAcceptsTemporary) {
        $model = storedAccountUser(['password' => $password, 'must_change_password' => $mustChangePassword]);

        $account = $this->mapper->toEntity($model, []);

        expect($account->mustChangePassword())->toBe($expectMustChange)
            ->and($account->acceptsTemporaryPassword())->toBe($expectAcceptsTemporary);
    })->with([
        'no password' => [null, false, false, true],
        'no password, flag left raised' => [null, true, false, true],
        'a temporary password' => ['$2y$04$stored.hash', true, true, true],
        'a chosen password' => ['$2y$04$stored.hash', false, false, false],
        'a password on a row written before the flag existed' => ['$2y$04$stored.hash', null, false, false],
    ]);

    it('carries the uuid, never the internal key, and no issued hash', function () {
        $account = $this->mapper->toEntity(storedAccountUser(['password' => '$2y$04$stored.hash', 'must_change_password' => true]), []);

        expect($account->id)->toBe('01930000-0000-7000-8000-0000000000c1')
            ->and($account->issuedPasswordHash())->toBeNull();
    });
});

describe('reading a stored user with a linked social identity', function () {
    it('restores an account that accepts no temporary password, although it holds no password', function () {
        $model = storedAccountUser(['password' => null, 'must_change_password' => false]);

        $account = $this->mapper->toEntity($model, [SocialProvider::Google]);

        expect($account->acceptsTemporaryPassword())->toBeFalse()
            ->and($account->mustChangePassword())->toBeFalse()
            ->and($account->issuedPasswordHash())->toBeNull();
    });

    it('refuses a temporary password whatever the password columns say', function (?string $password, ?bool $mustChangePassword) {
        $model = storedAccountUser(['password' => $password, 'must_change_password' => $mustChangePassword]);

        expect($this->mapper->toEntity($model, [SocialProvider::Google])->acceptsTemporaryPassword())->toBeFalse();
    })->with([
        'no password' => [null, false],
        'a temporary password' => ['$2y$04$stored.hash', true],
        'a chosen password' => ['$2y$04$stored.hash', false],
    ]);

    it('writes the same attributes back, with no column for the identity', function () {
        $account = $this->mapper->toEntity(storedAccountUser(['password' => null]), [SocialProvider::Google]);

        expect(array_keys($this->mapper->toAttributes($account)))
            ->toBe(['uuid', 'name', 'email', 'email_verified_at', 'deleted_at']);
    });
});

describe('the deletion request', function () {
    it('writes a null deleted_at for an active account, so saving never soft deletes it', function () {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);

        expect($this->mapper->toAttributes($account))->toHaveKey('deleted_at')
            ->and($this->mapper->toAttributes($account)['deleted_at'])->toBeNull();
    });

    it('writes the instant the deletion was requested into deleted_at', function () {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable);
        $requestedAt = new DateTimeImmutable('2026-01-01T12:00:00+00:00');
        $account->scheduleDeletion($requestedAt);

        expect($this->mapper->toAttributes($account)['deleted_at'])->toEqual($requestedAt);
    });

    it('writes deleted_at back to null once the account is reactivated, restoring the row', function () {
        $account = $this->mapper->toEntity(storedAccountUser(['deleted_at' => new DateTimeImmutable('2025-12-20T09:15:00+00:00')]), []);

        $account->reactivate();

        expect($this->mapper->toAttributes($account))->toHaveKey('deleted_at')
            ->and($this->mapper->toAttributes($account)['deleted_at'])->toBeNull();
    });

    it('reads a soft deleted user as an account scheduled for deletion at that instant', function () {
        $account = $this->mapper->toEntity(storedAccountUser(['deleted_at' => new DateTimeImmutable('2025-12-20T09:15:00+00:00')]), []);

        expect($account->isScheduledForDeletion())->toBeTrue()
            ->and($account->deletionRequestedAt())->toBeInstanceOf(DateTimeImmutable::class)
            ->and($account->deletionRequestedAt()->format(DATE_ATOM))->toBe('2025-12-20T09:15:00+00:00')
            ->and($account->gracePeriodEndsAt()->format(DATE_ATOM))->toBe('2026-01-19T09:15:00+00:00');
    });

    it('reads a user with no deleted_at as an active account', function () {
        $account = $this->mapper->toEntity(storedAccountUser(['deleted_at' => null]), []);

        expect($account->isScheduledForDeletion())->toBeFalse()
            ->and($account->deletionRequestedAt())->toBeNull();
    });

    it('reads a user row that carries no deleted_at column at all as an active account', function () {
        expect($this->mapper->toEntity(storedAccountUser([]), [])->isScheduledForDeletion())->toBeFalse();
    });

    it('round trips the deletion request unchanged', function () {
        $requestedAt = new DateTimeImmutable('2025-12-20T09:15:00+00:00');
        $account = $this->mapper->toEntity(storedAccountUser(['deleted_at' => $requestedAt]), []);

        expect($this->mapper->toAttributes($account)['deleted_at']->format(DATE_ATOM))->toBe($requestedAt->format(DATE_ATOM));
    });
});

describe('the two-factor status', function () {
    it('derives the status from the secret and its confirmation', function (?string $secret, ?DateTimeImmutable $confirmedAt, TwoFactorStatus $expected, bool $requiresSecondFactor) {
        $account = $this->mapper->toEntity(
            storedAccountUser(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => $confirmedAt]),
            [],
        );

        expect($account->twoFactorStatus())->toBe($expected)
            ->and($account->requiresSecondFactor())->toBe($requiresSecondFactor);
    })->with([
        'no secret and no confirmation' => [null, null, TwoFactorStatus::Disabled, false],
        'a secret still waiting for its first code' => ['encrypted-secret', null, TwoFactorStatus::Pending, false],
        'a secret confirmed with a code' => ['encrypted-secret', new DateTimeImmutable('2026-01-01T12:00:00+00:00'), TwoFactorStatus::Enabled, true],
        'a stale confirmation with the secret gone' => [null, new DateTimeImmutable('2026-01-01T12:00:00+00:00'), TwoFactorStatus::Disabled, false],
    ]);

    it('reads a user row that carries no two-factor columns at all as disabled', function () {
        expect($this->mapper->toEntity(storedAccountUser([]), [])->twoFactorStatus())->toBe(TwoFactorStatus::Disabled);
    });

    it('never writes a two-factor column, leaving them to Fortify, whatever the status', function (TwoFactorStatus $status) {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable, twoFactorStatus: $status);

        expect(twoFactorKeysIn($this->mapper->toAttributes($account)))->toBe([]);
    })->with([TwoFactorStatus::Disabled, TwoFactorStatus::Pending, TwoFactorStatus::Enabled]);

    it('never writes a two-factor column when a temporary password is issued either', function () {
        $account = Account::restore('account-uuid', 'Ada', 'ada@example.com', null, new DateTimeImmutable, twoFactorStatus: TwoFactorStatus::Enabled);
        $account->issueTemporaryPassword('temporary-hash');

        $attributes = $this->mapper->toAttributes($account);

        expect(twoFactorKeysIn($attributes))->toBe([])
            ->and($attributes)->toHaveKey('password');
    });

    it('writes an enabled account read from storage back with exactly the columns it always wrote', function () {
        $account = $this->mapper->toEntity(
            storedAccountUser([
                'two_factor_secret' => 'encrypted-secret',
                'two_factor_recovery_codes' => 'encrypted-codes',
                'two_factor_confirmed_at' => new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
            ]),
            [],
        );

        expect(array_keys($this->mapper->toAttributes($account)))
            ->toBe(['uuid', 'name', 'email', 'email_verified_at', 'deleted_at']);
    });
});

it('preserves accents and case exactly as the entity holds them', function () {
    $account = Account::restore('account-uuid', 'José Álvarez', 'jose@example.com', null, new DateTimeImmutable);

    expect($this->mapper->toAttributes($account)['name'])->toBe('José Álvarez');
});
