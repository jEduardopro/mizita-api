<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Application\Dtos\AuthenticateWithGoogleInput;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\ValueObjects\GoogleIdentity;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use DateTimeImmutable;

/**
 * The fixed cast of a Google sign-in, shared by the two use case tests.
 *
 * Everything here is a literal a test can name in an assertion: the same
 * subject, the same instant, the same two generated ids. A builder rather than
 * a data provider, so each test overrides only the one field it is about.
 */
final class GoogleFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    /** Google's stable subject claim, the only thing a returning person is matched on. */
    public const SUB = '104729183746501928374';

    public const EMAIL = 'ada@example.com';

    public const NAME = 'Ada Lovelace';

    /** First id handed out by the generator: the account, on the register path. */
    public const GENERATED_ACCOUNT_ID = '01930000-0000-7000-8000-000000000001';

    /** Second id handed out by the generator: the social identity. */
    public const GENERATED_IDENTITY_ID = '01930000-0000-7000-8000-000000000002';

    public const EXISTING_ACCOUNT_ID = 'existing-account-uuid';

    public const EXISTING_IDENTITY_ID = 'existing-identity-uuid';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    /**
     * Built the only way an input can be built: through the value object that
     * proves the subject and the email are there. The sealed constructor is the
     * point, so the fixture goes the same way every caller does.
     */
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

    /**
     * An account as a repository would hand it back: restored, never created.
     */
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
