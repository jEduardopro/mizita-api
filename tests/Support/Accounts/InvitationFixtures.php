<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Domains\Accounts\ValueObjects\TemporaryPassword;
use DateTimeImmutable;

final class InvitationFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const NAME = 'Ada Lovelace';

    public const EMAIL = 'ada@example.com';

    public const GENERATED_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000c1';

    public const EXISTING_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000c2';

    public const TEMPORARY_PASSWORD = 'Tq7mW2xK9pLr4ZvB8nYd';

    public const TEMPORARY_PASSWORD_HASH = '$2y$04$temporary.password.hash.issued.by.the.fake';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function temporaryPassword(): TemporaryPassword
    {
        return TemporaryPassword::fromString(self::TEMPORARY_PASSWORD);
    }

    /**
     * @param  list<SocialProvider>  $linkedSocialProviders
     */
    public static function storedAccount(
        PasswordStatus $passwordStatus = PasswordStatus::Absent,
        array $linkedSocialProviders = [],
    ): Account {
        return Account::restore(
            id: self::EXISTING_ACCOUNT_ID,
            name: self::NAME,
            email: self::EMAIL,
            emailVerifiedAt: null,
            createdAt: new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            passwordStatus: $passwordStatus,
            linkedSocialProviders: $linkedSocialProviders,
        );
    }
}
