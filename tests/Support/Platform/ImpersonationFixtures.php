<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\ValueObjects\BusinessOwnerAccount;
use App\Domains\Platform\ValueObjects\Impersonation;
use DateTimeImmutable;

final class ImpersonationFixtures
{
    public const IMPERSONATION_ID = '01930000-0000-7000-8000-0000000a1001';

    public const ADMIN_ID = '01930000-0000-7000-8000-0000000ad001';

    public const OTHER_ADMIN_ID = '01930000-0000-7000-8000-0000000ad002';

    public const ACCOUNT_ID = '01930000-0000-7000-8000-0000000ac001';

    public const OTHER_ACCOUNT_ID = '01930000-0000-7000-8000-0000000ac002';

    public const BUSINESS_ID = '01930000-0000-7000-8000-0000000ab001';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000ab002';

    public const IP_ADDRESS = '203.0.113.7';

    public const BUSINESS_NAME = 'Barbería Ñandú';

    public const OWNER_NAME = 'Ada Lovelace';

    public const NOW = '2026-09-25T15:00:00+00:00';

    public const EXPIRES_AT = '2026-09-25T16:00:00+00:00';

    public const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function owner(
        string $businessId = self::BUSINESS_ID,
        string $accountId = self::ACCOUNT_ID,
    ): BusinessOwnerAccount {
        return new BusinessOwnerAccount(
            businessId: $businessId,
            businessName: self::BUSINESS_NAME,
            accountId: $accountId,
            ownerName: self::OWNER_NAME,
        );
    }

    public static function begun(
        DateTimeImmutable $now = new DateTimeImmutable(self::NOW),
        string $adminId = self::ADMIN_ID,
        string $id = self::IMPERSONATION_ID,
    ): Impersonation {
        return Impersonation::begin($id, $adminId, self::owner(), $now);
    }
}
