<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final readonly class SlotRules
{
    public const HARD_CAP_DAYS = 365;

    private const MINUTES_PER_DAY = TimeOfDay::HOURS_PER_DAY * TimeOfDay::MINUTES_PER_HOUR;

    private const HARD_CAP_MINUTES = self::HARD_CAP_DAYS * self::MINUTES_PER_DAY;

    private const NO_LEFTOVER_MINUTES = 0;

    public function __construct(
        public int $leadTimeMinutes,
        public ?int $bookingWindowMinutes,
        public int $slotGranularityMinutes,
    ) {}

    public function lastBookableStart(DateTimeImmutable $now, DateTimeZone $zone): DateTimeImmutable
    {
        return $now->setTimezone($zone)->add($this->bookingHorizon());
    }

    public function bookingHorizon(): DateInterval
    {
        if ($this->bookingWindowMinutes === null) {
            return self::horizonOf(self::HARD_CAP_DAYS, self::NO_LEFTOVER_MINUTES);
        }

        $window = min($this->bookingWindowMinutes, self::HARD_CAP_MINUTES);

        return self::horizonOf(
            intdiv($window, self::MINUTES_PER_DAY),
            $window % self::MINUTES_PER_DAY,
        );
    }

    private static function horizonOf(int $days, int $minutes): DateInterval
    {
        return new DateInterval('P'.$days.'DT'.$minutes.'M');
    }
}
