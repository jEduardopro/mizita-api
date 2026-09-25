<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

use DateInterval;
use DateTimeImmutable;

final class DeletionGracePeriod
{
    public const int DAYS = 30;

    public static function endingFrom(DateTimeImmutable $start): DateTimeImmutable
    {
        return $start->add(new DateInterval('P'.self::DAYS.'D'));
    }
}
