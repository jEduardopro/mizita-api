<?php

declare(strict_types=1);

namespace App\Domains\Availability\Services;

use App\Domains\Availability\ValueObjects\OpenState;
use App\Domains\Availability\ValueObjects\TimeOfDay;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use DateTimeImmutable;
use DateTimeZone;

final class OpeningHoursCalendar
{
    private const ISO_WEEKDAY = 'N';

    private const LOCAL_HOUR = 'G';

    private const LOCAL_MINUTE = 'i';

    private const DAYS_PER_WEEK = 7;

    public function stateAt(WeeklyIntervals $hours, DateTimeZone $zone, DateTimeImmutable $instant): OpenState
    {
        $local = $instant->setTimezone($zone);
        $weekday = Weekday::from((int) $local->format(self::ISO_WEEKDAY));
        $minute = self::minutesFromMidnight($local);

        $closesAt = self::closingMinuteAt($hours->forWeekday($weekday), $minute);

        if ($closesAt !== null) {
            return OpenState::openUntil(self::timeAt($closesAt));
        }

        return self::nextOpening($hours, $weekday, $minute);
    }

    private static function nextOpening(WeeklyIntervals $hours, Weekday $weekday, int $minute): OpenState
    {
        $laterToday = self::firstOpeningAfter($hours->forWeekday($weekday), $minute);

        if ($laterToday !== null) {
            return OpenState::closedUntil($weekday, self::timeAt($laterToday));
        }

        for ($daysAhead = 1; $daysAhead <= self::DAYS_PER_WEEK; $daysAhead++) {
            $day = self::weekdayAfter($weekday, $daysAhead);
            $opensAt = self::firstOpening($hours->forWeekday($day));

            if ($opensAt !== null) {
                return OpenState::closedUntil($day, self::timeAt($opensAt));
            }
        }

        return OpenState::closedIndefinitely();
    }

    /**
     * @param  list<array{int, int}>  $intervals
     */
    private static function closingMinuteAt(array $intervals, int $minute): ?int
    {
        foreach ($intervals as [$opensAt, $closesAt]) {
            if ($minute >= $opensAt && $minute < $closesAt) {
                return $closesAt;
            }
        }

        return null;
    }

    /**
     * @param  list<array{int, int}>  $intervals
     */
    private static function firstOpeningAfter(array $intervals, int $minute): ?int
    {
        foreach ($intervals as [$opensAt]) {
            if ($opensAt > $minute) {
                return $opensAt;
            }
        }

        return null;
    }

    /**
     * @param  list<array{int, int}>  $intervals
     */
    private static function firstOpening(array $intervals): ?int
    {
        return $intervals === [] ? null : $intervals[0][0];
    }

    private static function weekdayAfter(Weekday $weekday, int $daysAhead): Weekday
    {
        return Weekday::from(($weekday->value + $daysAhead - 1) % self::DAYS_PER_WEEK + 1);
    }

    private static function minutesFromMidnight(DateTimeImmutable $local): int
    {
        return (int) $local->format(self::LOCAL_HOUR) * TimeOfDay::MINUTES_PER_HOUR
            + (int) $local->format(self::LOCAL_MINUTE);
    }

    private static function timeAt(int $minute): TimeOfDay
    {
        return TimeOfDay::restore(
            intdiv($minute, TimeOfDay::MINUTES_PER_HOUR),
            $minute % TimeOfDay::MINUTES_PER_HOUR,
        );
    }
}
