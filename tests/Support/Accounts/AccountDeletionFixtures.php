<?php

declare(strict_types=1);

namespace Tests\Support\Accounts;

use App\Domains\Accounts\Application\Dtos\DeleteAccountInput;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\ValueObjects\ClosedBusinessSnapshot;
use App\Domains\Accounts\ValueObjects\OwnedBusinessSnapshot;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use DateTimeImmutable;

final class AccountDeletionFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const GRACE_PERIOD_ENDS_AT = '2026-01-31T12:00:00+00:00';

    public const DELETION_REQUESTED_AT = '2025-12-20T09:15:00+00:00';

    public const REQUESTED_GRACE_PERIOD_ENDS_AT = '2026-01-19T09:15:00+00:00';

    public const ACCOUNT_ID = '01930000-0000-7000-8000-00000000ac01';

    public const BUSINESS_ID = '01930000-0000-7000-8000-0000000000b1';

    public const NAME = 'Ada Lovelace';

    public const EMAIL = 'ada@example.com';

    public const PASSWORD = 'Correct1!Pass';

    public const BUSINESS_NAME = 'Estudio Peñalver';

    public const CLOSED_AT = '2025-12-20T09:15:00+00:00';

    public const PURGE_SCHEDULED_AT = '2026-01-19T09:15:00+00:00';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    /**
     * @param  list<SocialProvider>  $linkedSocialProviders
     */
    public static function activeAccount(
        PasswordStatus $passwordStatus = PasswordStatus::Chosen,
        string $email = self::EMAIL,
        array $linkedSocialProviders = [],
    ): Account {
        return Account::restore(
            id: self::ACCOUNT_ID,
            name: self::NAME,
            email: $email,
            emailVerifiedAt: new DateTimeImmutable('2025-06-01T08:30:00+00:00'),
            createdAt: new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            passwordStatus: $passwordStatus,
            linkedSocialProviders: $linkedSocialProviders,
        );
    }

    public static function scheduledAccount(
        string $deletionRequestedAt = self::DELETION_REQUESTED_AT,
        PasswordStatus $passwordStatus = PasswordStatus::Chosen,
    ): Account {
        return Account::restore(
            id: self::ACCOUNT_ID,
            name: self::NAME,
            email: self::EMAIL,
            emailVerifiedAt: new DateTimeImmutable('2025-06-01T08:30:00+00:00'),
            createdAt: new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            passwordStatus: $passwordStatus,
            deletionRequestedAt: new DateTimeImmutable($deletionRequestedAt),
        );
    }

    public static function ownedBusiness(): OwnedBusinessSnapshot
    {
        return new OwnedBusinessSnapshot(self::BUSINESS_ID, self::BUSINESS_NAME);
    }

    public static function closedBusiness(bool $purged = false): ClosedBusinessSnapshot
    {
        return new ClosedBusinessSnapshot(
            id: self::BUSINESS_ID,
            name: self::BUSINESS_NAME,
            closedAt: new DateTimeImmutable(self::CLOSED_AT),
            purgeScheduledAt: new DateTimeImmutable(self::PURGE_SCHEDULED_AT),
            purged: $purged,
        );
    }

    public static function confirmedByPassword(string $password = self::PASSWORD): DeleteAccountInput
    {
        return new DeleteAccountInput(self::ACCOUNT_ID, $password, null);
    }

    public static function confirmedByEmail(string $email = self::EMAIL): DeleteAccountInput
    {
        return new DeleteAccountInput(self::ACCOUNT_ID, null, $email);
    }
}
