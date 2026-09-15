<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use App\Domains\Staff\ValueObjects\StaffRole;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class StaffFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const MEMBER_ID = '01930000-0000-7000-8000-0000000000d1';

    public const SECOND_MEMBER_ID = '01930000-0000-7000-8000-0000000000d2';

    public const THIRD_MEMBER_ID = '01930000-0000-7000-8000-0000000000d3';

    public const ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a1';

    public const SECOND_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a2';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function member(
        string $id = self::MEMBER_ID,
        string $accountId = self::ACCOUNT_ID,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        StaffRole $role = StaffRole::Owner,
        ?DateTimeImmutable $createdAt = null,
    ): StaffMember {
        return StaffMember::restore(
            id: $id,
            businessId: $businessId,
            accountId: $accountId,
            role: $role,
            createdAt: $createdAt ?? self::now(),
        );
    }

    public static function account(
        string $id = self::ACCOUNT_ID,
        string $name = 'Ada Lovelace',
        string $email = 'ada@example.com',
    ): AccountSnapshot {
        return new AccountSnapshot($id, $name, $email);
    }
}
