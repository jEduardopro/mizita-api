<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

use DateInterval;
use DateTimeImmutable;

final readonly class ManageTokenExpiry
{
    public const GRACE_HOURS = 24;

    private function __construct() {}

    public static function forSlot(AppointmentSlot $slot): DateTimeImmutable
    {
        return $slot->startsAt->add(new DateInterval('PT'.self::GRACE_HOURS.'H'));
    }
}
