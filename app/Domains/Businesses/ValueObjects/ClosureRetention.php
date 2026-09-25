<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

use DateInterval;
use DateTimeImmutable;

final class ClosureRetention
{
    public const int DAYS = 30;

    public static function purgeDueAt(DateTimeImmutable $closedAt): DateTimeImmutable
    {
        return $closedAt->add(self::period());
    }

    public static function closedNoLaterThanFor(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->sub(self::period());
    }

    private static function period(): DateInterval
    {
        return new DateInterval('P'.self::DAYS.'D');
    }
}
