<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Entities\Business;
use DateTimeImmutable;

final class ClosureFixtures
{
    public const CLOSED_AT = '2026-02-01T10:00:00+00:00';

    public const PURGE_DUE_AT = '2026-03-03T10:00:00+00:00';

    public const ONE_SECOND_BEFORE_PURGE_DUE = '2026-03-03T09:59:59+00:00';

    public const STRANGER_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a9';

    public static function closedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::CLOSED_AT);
    }

    public static function purgeDueAt(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::PURGE_DUE_AT);
    }

    public static function closedBusiness(
        string $id = OnboardingFixtures::GENERATED_BUSINESS_ID,
        string $name = OnboardingFixtures::NAME,
        string $closedBy = OnboardingFixtures::OWNER_ACCOUNT_ID,
        string $closedAt = self::CLOSED_AT,
    ): Business {
        $business = OnboardingFixtures::business(id: $id, name: $name);
        $business->close($closedBy, new DateTimeImmutable($closedAt));

        return $business;
    }

    public static function purgedBusiness(
        string $id = OnboardingFixtures::GENERATED_BUSINESS_ID,
        string $closedBy = OnboardingFixtures::OWNER_ACCOUNT_ID,
    ): Business {
        $business = self::closedBusiness(id: $id, closedBy: $closedBy);
        $business->markPurged(self::purgeDueAt());

        return $business;
    }
}
