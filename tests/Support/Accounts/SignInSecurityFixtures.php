<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Application\Dtos\PasskeyData;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\RegisteredPasskey;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;
use DateTimeImmutable;

final class SignInSecurityFixtures
{
    public const ACCOUNT_ID = '01930000-0000-7000-8000-0000000005a1';

    public const OTHER_ACCOUNT_ID = '01930000-0000-7000-8000-0000000005a2';

    public const PASSKEY_ID = '01930000-0000-7000-8000-0000000005b1';

    public const SECOND_PASSKEY_ID = '01930000-0000-7000-8000-0000000005b2';

    public const FOREIGN_PASSKEY_ID = '01930000-0000-7000-8000-0000000005b3';

    public const PASSKEY_NAME = 'MacBook de José';

    public const SECOND_PASSKEY_NAME = 'Pixel 9';

    public const AUTHENTICATOR = 'iCloud Keychain';

    public const CREATED_AT = '2026-01-01T12:00:00+00:00';

    public const LAST_USED_AT = '2026-02-14T08:45:00+00:00';

    public static function account(
        PasswordStatus $passwordStatus = PasswordStatus::Chosen,
        TwoFactorStatus $twoFactorStatus = TwoFactorStatus::Enabled,
    ): Account {
        return Account::restore(
            id: self::ACCOUNT_ID,
            name: 'Ada Lovelace',
            email: 'ada@example.com',
            emailVerifiedAt: new DateTimeImmutable('2025-06-01T08:30:00+00:00'),
            createdAt: new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            passwordStatus: $passwordStatus,
            twoFactorStatus: $twoFactorStatus,
        );
    }

    public static function passkey(
        string $id = self::PASSKEY_ID,
        string $name = self::PASSKEY_NAME,
        ?string $authenticator = self::AUTHENTICATOR,
        ?string $lastUsedAt = self::LAST_USED_AT,
    ): RegisteredPasskey {
        return new RegisteredPasskey(
            id: $id,
            name: $name,
            authenticator: $authenticator,
            createdAt: new DateTimeImmutable(self::CREATED_AT),
            lastUsedAt: $lastUsedAt === null ? null : new DateTimeImmutable($lastUsedAt),
        );
    }

    public static function passkeyData(
        string $id = self::PASSKEY_ID,
        string $name = self::PASSKEY_NAME,
        ?string $authenticator = self::AUTHENTICATOR,
        ?string $lastUsedAt = self::LAST_USED_AT,
    ): PasskeyData {
        return PasskeyData::fromRegisteredPasskey(self::passkey($id, $name, $authenticator, $lastUsedAt));
    }
}
