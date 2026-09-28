<?php

declare(strict_types=1);

namespace Tests\Support\Subscriptions;

use App\Domains\Subscriptions\Entities\Subscription;
use App\Domains\Subscriptions\ValueObjects\Plan;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPeriod;
use App\Domains\Subscriptions\ValueObjects\SubscriptionPrice;
use App\Domains\Subscriptions\ValueObjects\SubscriptionStatus;
use App\Shared\ValueObjects\CurrencyCode;
use DateTimeImmutable;

final class SubscriptionFixtures
{
    public const BUSINESS_ID = '01930000-0000-7000-8000-00000000b001';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-00000000b002';

    public const SUBSCRIPTION_ID = '01930000-0000-7000-8000-00000000c001';

    public const OTHER_SUBSCRIPTION_ID = '01930000-0000-7000-8000-00000000c002';

    public const GENERATED_SUBSCRIPTION_ID = '01930000-0000-7000-8000-00000000c0ff';

    public const SLUG = 'barberia-centro';

    public const OTHER_SLUG = 'estetica-norte';

    public const TIMEZONE = 'America/Mexico_City';

    public const OTHER_TIMEZONE = 'Europe/Madrid';

    public const NOW = '2026-06-15T15:00:00+00:00';

    public const STARTS_AT = '2026-06-01T15:00:00+00:00';

    public const ENDS_AT = '2026-07-01T06:00:00+00:00';

    public const CREATED_AT = '2026-06-01T15:00:00+00:00';

    public const COMPLETE_LIST_PRICE = 20000;

    public static function instant(string $atom): DateTimeImmutable
    {
        return new DateTimeImmutable($atom);
    }

    public static function now(): DateTimeImmutable
    {
        return self::instant(self::NOW);
    }

    public static function subscription(
        SubscriptionStatus $status = SubscriptionStatus::Active,
        ?string $endsAt = self::ENDS_AT,
        string $startsAt = self::STARTS_AT,
        string $id = self::SUBSCRIPTION_ID,
        string $businessId = self::BUSINESS_ID,
        Plan $plan = Plan::Complete,
        int $amount = self::COMPLETE_LIST_PRICE,
    ): Subscription {
        return Subscription::restore(
            id: $id,
            businessId: $businessId,
            plan: $plan,
            status: $status,
            period: SubscriptionPeriod::restore(
                self::instant($startsAt),
                $endsAt === null ? null : self::instant($endsAt),
            ),
            price: SubscriptionPrice::restore($amount, CurrencyCode::default()),
            createdAt: self::instant(self::CREATED_AT),
        );
    }

    public static function directory(): FakeBusinessDirectory
    {
        return (new FakeBusinessDirectory)
            ->with(self::SLUG, self::BUSINESS_ID, self::TIMEZONE)
            ->with(self::OTHER_SLUG, self::OTHER_BUSINESS_ID, self::OTHER_TIMEZONE);
    }
}
