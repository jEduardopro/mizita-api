<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Laravel\Fortify\Contracts\PasskeyUser;
use Tests\TestCase;

uses(TestCase::class);

const USER_TEMPORARY_PASSWORD = 'Tq7mW2xK9pLr4ZvB8nYd';

describe('the temporary password an owner may copy', function () {
    it('holds it encrypted, never as the plaintext', function () {
        $account = (new User)->forceFill(['temporary_password' => USER_TEMPORARY_PASSWORD]);

        $stored = $account->getAttributes()['temporary_password'];

        expect($stored)->toBeString()
            ->and($stored)->not->toBe(USER_TEMPORARY_PASSWORD)
            ->and($stored)->not->toContain(USER_TEMPORARY_PASSWORD)
            ->and(Crypt::decryptString($stored))->toBe(USER_TEMPORARY_PASSWORD);
    });

    it('reads it back decrypted', function () {
        $account = (new User)->forceFill(['temporary_password' => USER_TEMPORARY_PASSWORD]);

        expect($account->temporary_password)->toBe(USER_TEMPORARY_PASSWORD);
    });

    it('stores a discarded one as null rather than as an encrypted empty value', function () {
        $account = (new User)->forceFill(['temporary_password' => USER_TEMPORARY_PASSWORD]);

        $account->forceFill(['temporary_password' => null]);

        expect($account->getAttributes()['temporary_password'])->toBeNull()
            ->and($account->temporary_password)->toBeNull();
    });

    it('never serializes it, encrypted or not', function () {
        $account = (new User)->forceFill([
            'uuid' => '01930000-0000-7000-8000-0000000000c2',
            'temporary_password' => USER_TEMPORARY_PASSWORD,
        ]);

        expect($account->toArray())->not->toHaveKey('temporary_password')
            ->and($account->toJson())->not->toContain(USER_TEMPORARY_PASSWORD)
            ->and($account->toJson())->not->toContain($account->getAttributes()['temporary_password']);
    });

    it('cannot be mass assigned from a payload', function () {
        $account = new User(['temporary_password' => USER_TEMPORARY_PASSWORD]);

        expect($account->getAttributes())->not->toHaveKey('temporary_password');
    });
});

const USER_TWO_FACTOR_SECRET = 'eyJpdiI6InR3by1mYWN0b3Itc2VjcmV0In0=';

const USER_RECOVERY_CODES = 'eyJpdiI6InJlY292ZXJ5LWNvZGVzIn0=';

function accountWithSecondFactor(?string $confirmedAt): User
{
    return (new User)->forceFill([
        'uuid' => '01930000-0000-7000-8000-0000000000c3',
        'two_factor_secret' => USER_TWO_FACTOR_SECRET,
        'two_factor_recovery_codes' => USER_RECOVERY_CODES,
        'two_factor_confirmed_at' => $confirmedAt,
    ]);
}

describe('the second factor secrets it stores', function () {
    it('never serializes them as array keys', function (string $column) {
        expect(accountWithSecondFactor('2026-09-01 10:00:00')->toArray())->not->toHaveKey($column);
    })->with(['two_factor_secret', 'two_factor_recovery_codes']);

    it('never lets their values reach the json form', function (string $value) {
        expect(accountWithSecondFactor('2026-09-01 10:00:00')->toJson())->not->toContain($value);
    })->with([USER_TWO_FACTOR_SECRET, USER_RECOVERY_CODES]);

    it('cannot have them mass assigned from a payload', function (string $column) {
        $account = new User([$column => 'attacker-chosen']);

        expect($account->getAttributes())->not->toHaveKey($column);
    })->with(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);

    it('reads the confirmation instant back as a date', function () {
        expect(accountWithSecondFactor('2026-09-01 10:00:00')->two_factor_confirmed_at)
            ->toBeInstanceOf(DateTimeInterface::class);
    });
});

describe('whether two factor authentication is on', function () {
    it('counts it as enabled once the secret is confirmed', function () {
        expect(accountWithSecondFactor('2026-09-01 10:00:00')->hasEnabledTwoFactorAuthentication())->toBeTrue();
    });

    it('does not count a secret that was never confirmed', function () {
        expect(accountWithSecondFactor(null)->hasEnabledTwoFactorAuthentication())->toBeFalse();
    });

    it('does not count an account that never set a secret', function () {
        expect((new User)->hasEnabledTwoFactorAuthentication())->toBeFalse();
    });
});

describe('the passkey contract', function () {
    it('is a passkey user in the sense fortify requires', function () {
        expect(new User)->toBeInstanceOf(PasskeyUser::class);
    });

    it('owns its passkeys through the application passkey model', function () {
        $passkeys = (new User)->passkeys();

        expect($passkeys->getRelated())->toBeInstanceOf(PasskeyModel::class)
            ->and($passkeys->getForeignKeyName())->toBe('user_id');
    });
});
