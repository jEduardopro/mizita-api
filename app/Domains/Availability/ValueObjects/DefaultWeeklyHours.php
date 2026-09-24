<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

use App\Domains\Availability\Exceptions\InvalidTimeOfDay;

final readonly class DefaultWeeklyHours
{
    private const OPENS_AT = '09:00';

    private const CLOSES_AT = '18:00';

    private const WORKING_DAYS = [
        Weekday::Monday,
        Weekday::Tuesday,
        Weekday::Wednesday,
        Weekday::Thursday,
        Weekday::Friday,
    ];

    /**
     * @return list<ScheduleInterval>
     *
     * @throws InvalidTimeOfDay
     */
    public static function intervals(): array
    {
        $opensAt = TimeOfDay::fromString(self::OPENS_AT);
        $closesAt = TimeOfDay::fromString(self::CLOSES_AT);

        return array_map(
            static fn (Weekday $weekday): ScheduleInterval => new ScheduleInterval(
                weekday: $weekday,
                startsAt: $opensAt,
                endsAt: $closesAt,
            ),
            self::WORKING_DAYS,
        );
    }
}
