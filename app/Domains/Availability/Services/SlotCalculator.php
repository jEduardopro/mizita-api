<?php

declare(strict_types=1);

namespace App\Domains\Availability\Services;

use App\Domains\Availability\ValueObjects\AvailableDay;
use App\Domains\Availability\ValueObjects\BookedInterval;
use App\Domains\Availability\ValueObjects\BookingBlock;
use App\Domains\Availability\ValueObjects\LocalDateRange;
use App\Domains\Availability\ValueObjects\SlotRules;
use App\Domains\Availability\ValueObjects\TimeOfDay;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class SlotCalculator
{
    private const UTC = 'UTC';

    private const ISO_WEEKDAY = 'N';

    private const WALL_CLOCK = 'H:i';

    private const WALL_CLOCK_SECONDS = ':00';

    private readonly DateTimeZone $utc;

    public function __construct()
    {
        $this->utc = new DateTimeZone(self::UTC);
    }

    /**
     * @param  list<BookedInterval>  $booked
     * @return list<AvailableDay>
     */
    public function slotsBetween(
        LocalDateRange $range,
        WeeklyIntervals $workingHours,
        array $booked,
        BookingBlock $block,
        SlotRules $rules,
        DateTimeZone $zone,
        DateTimeImmutable $now,
    ): array {
        $earliest = $this->earliestStart($now, $rules);
        $latest = $this->latestStart($now, $rules, $zone);

        $days = [];

        foreach ($range->dates() as $date) {
            $days[] = new AvailableDay(
                $date,
                $this->startsOn($date, $workingHours, $booked, $block, $rules, $zone, $earliest, $latest),
            );
        }

        return $days;
    }

    /**
     * @param  list<BookedInterval>  $booked
     * @return list<DateTimeImmutable>
     */
    private function startsOn(
        string $date,
        WeeklyIntervals $workingHours,
        array $booked,
        BookingBlock $block,
        SlotRules $rules,
        DateTimeZone $zone,
        DateTimeImmutable $earliest,
        DateTimeImmutable $latest,
    ): array {
        $starts = [];

        foreach ($workingHours->forWeekday($this->weekdayOf($date)) as [$opensAt, $closesAt]) {
            foreach ($this->gridWithin($opensAt, $closesAt, $block, $rules) as $minute) {
                $wallClock = $this->wallClockAt($minute);
                $start = $this->utcInstantFor($date, $wallClock, $zone);

                if (! $this->existsInZone($start, $wallClock, $zone)) {
                    continue;
                }

                if (! $this->withinBounds($start, $earliest, $latest)) {
                    continue;
                }

                if ($this->collides($start, $block, $booked)) {
                    continue;
                }

                $starts[] = $start;
            }
        }

        return $starts;
    }

    /**
     * @return list<int>
     */
    private function gridWithin(int $opensAt, int $closesAt, BookingBlock $block, SlotRules $rules): array
    {
        $minutes = [];
        $total = $block->totalMinutes();

        for ($blockStart = $opensAt; $blockStart + $total <= $closesAt; $blockStart += $rules->slotGranularityMinutes) {
            $minutes[] = $blockStart + $block->bufferBeforeMinutes;
        }

        return $minutes;
    }

    private function earliestStart(DateTimeImmutable $now, SlotRules $rules): DateTimeImmutable
    {
        return $now->add($this->minutes($rules->leadTimeMinutes));
    }

    private function latestStart(DateTimeImmutable $now, SlotRules $rules, DateTimeZone $zone): DateTimeImmutable
    {
        return $rules->lastBookableStart($now, $zone)->setTimezone($this->utc);
    }

    private function utcInstantFor(string $date, string $wallClock, DateTimeZone $zone): DateTimeImmutable
    {
        $local = new DateTimeImmutable($date.' '.$wallClock.self::WALL_CLOCK_SECONDS, $zone);

        return $local->setTimezone($this->utc);
    }

    private function existsInZone(DateTimeImmutable $start, string $wallClock, DateTimeZone $zone): bool
    {
        return $start->setTimezone($zone)->format(self::WALL_CLOCK) === $wallClock;
    }

    private function withinBounds(
        DateTimeImmutable $start,
        DateTimeImmutable $earliest,
        DateTimeImmutable $latest,
    ): bool {
        return $start >= $earliest && $start <= $latest;
    }

    /**
     * @param  list<BookedInterval>  $booked
     */
    private function collides(DateTimeImmutable $start, BookingBlock $block, array $booked): bool
    {
        $from = $start->sub($this->minutes($block->bufferBeforeMinutes));
        $to = $start->add($this->minutes($block->minutesAfterStart()));

        foreach ($booked as $interval) {
            if ($interval->overlaps($from, $to)) {
                return true;
            }
        }

        return false;
    }

    private function weekdayOf(string $date): Weekday
    {
        return Weekday::from((int) (new DateTimeImmutable($date, $this->utc))->format(self::ISO_WEEKDAY));
    }

    private function wallClockAt(int $minute): string
    {
        return sprintf(
            '%02d:%02d',
            intdiv($minute, TimeOfDay::MINUTES_PER_HOUR),
            $minute % TimeOfDay::MINUTES_PER_HOUR,
        );
    }

    private function minutes(int $count): DateInterval
    {
        return new DateInterval('PT'.$count.'M');
    }
}
