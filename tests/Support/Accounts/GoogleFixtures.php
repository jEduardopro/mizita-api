<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Application\Dtos\AuthenticateWithGoogleInput;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\ValueObjects\GoogleIdentity;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use DateTimeImmutable;

final class GoogleFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const SUB = '104729183746501928374';

    public const EMAIL = 'ada@example.com';

    public const NAME = 'Ada Lovelace';

    public const GENERATED_ACCOUNT_ID = '01930000-0000-7000-8000-000000000001';

    public const GENERATED_IDENTITY_ID = '01930000-0000-7000-8000-000000000002';

    public const EXISTING_ACCOUNT_ID = 'existing-account-uuid';

    public const EXISTING_IDENTITY_ID = 'existing-identity-uuid';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function input(
        string $googleUserId = self::SUB,
        string $email = self::EMAIL,
        bool $emailVerified = true,
        string $name = self::NAME,
    ): AuthenticateWithGoogleInput {
        return AuthenticateWithGoogleInput::fromGoogleIdentity(
            self::identity($googleUserId, $email, $emailVerified, $name),
        );
    }

    public static function identity(
        string $sub = self::SUB,
        string $email = self::EMAIL,
        bool $emailVerified = true,
        string $name = self::NAME,
        ?string $avatarUrl = null,
    ): GoogleIdentity {
        return new GoogleIdentity($sub, $email, $emailVerified, $name, $avatarUrl);
    }

    public static function storedAccount(
        string $id = self::EXISTING_ACCOUNT_ID,
        string $name = self::NAME,
        string $email = self::EMAIL,
        ?DateTimeImmutable $emailVerifiedAt = null,
    ): Account {
        return Account::restore($id, $name, $email, $emailVerifiedAt, new DateTimeImmutable('2025-05-01T08:30:00+00:00'));
    }

    public static function storedIdentity(string $accountId = self::EXISTING_ACCOUNT_ID): SocialIdentity
    {
        return SocialIdentity::restore(
            self::EXISTING_IDENTITY_ID,
            $accountId,
            SocialProvider::Google,
            self::SUB,
            new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
        );
    }
}
