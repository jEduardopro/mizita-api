<?php

declare(strict_types=1);

namespace Tests\Support\Platform;

use App\Domains\Platform\ValueObjects\PlatformBusinessOwner;
use App\Domains\Platform\ValueObjects\PlatformBusinessRecord;
use DateTimeImmutable;

final class PlatformBusinessFixtures
{
    public const BUSINESS_ID = '01930000-0000-7000-8000-00000000e001';

    public const SECOND_BUSINESS_ID = '01930000-0000-7000-8000-00000000e002';

    public const THIRD_BUSINESS_ID = '01930000-0000-7000-8000-00000000e003';

    public const BUSINESS_NAME = 'Barbería Centro';

    public const BUSINESS_SLUG = 'barberia-centro';

    public const OWNER_NAME = 'Ada Lovelace';

    public const OWNER_EMAIL = 'ada@barberia-centro.test';

    public const CREATED_AT = '2026-03-14T09:30:00+00:00';

    public const NOW = '2026-09-25T15:00:00+00:00';

    public const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function owner(
        string $name = self::OWNER_NAME,
        string $email = self::OWNER_EMAIL,
    ): PlatformBusinessOwner {
        return new PlatformBusinessOwner($name, $email);
    }

    public static function business(
        string $id = self::BUSINESS_ID,
        string $name = self::BUSINESS_NAME,
        string $slug = self::BUSINESS_SLUG,
        string $createdAt = self::CREATED_AT,
        ?PlatformBusinessOwner $owner = new PlatformBusinessOwner(self::OWNER_NAME, self::OWNER_EMAIL),
        int $servicesCount = 4,
        int $customersCount = 37,
    ): PlatformBusinessRecord {
        return new PlatformBusinessRecord(
            id: $id,
            name: $name,
            slug: $slug,
            createdAt: new DateTimeImmutable($createdAt),
            owner: $owner,
            servicesCount: $servicesCount,
            customersCount: $customersCount,
        );
    }
}
