<?php

declare(strict_types=1);

namespace App\Domains\Statistics\ValueObjects;

use App\Domains\Statistics\Exceptions\InvalidStatisticsPeriod;
use App\Domains\Statistics\Exceptions\StatisticsPeriodTooWide;
use DateTimeZone;

final readonly class StatisticsPeriod
{
    public const MAXIMUM_DAYS = 366;

    private function __construct(
        public LocalDate $from,
        public LocalDate $to,
    ) {}

    /**
     * @throws InvalidStatisticsPeriod
     * @throws StatisticsPeriodTooWide
     */
    public static function between(LocalDate $from, LocalDate $to): self
    {
        if ($from->isAfter($to)) {
            throw InvalidStatisticsPeriod::inverted($from->toString(), $to->toString());
        }

        $days = $from->daysThrough($to);

        if ($days > self::MAXIMUM_DAYS) {
            throw StatisticsPeriodTooWide::spanning($days, self::MAXIMUM_DAYS);
        }

        return new self($from, $to);
    }

    public static function monthToDate(LocalDate $today): self
    {
        return new self($today->firstOfMonth(), $today);
    }

    public static function singleDay(LocalDate $day): self
    {
        return new self($day, $day);
    }

    public static function trailingDaysEndingOn(LocalDate $lastDay, int $days): self
    {
        return new self($lastDay->minusDays($days - 1), $lastDay);
    }

    public function previousMonth(): self
    {
        return new self($this->from->sameDayOfPreviousMonth(), $this->to->sameDayOfPreviousMonth());
    }

    /**
     * @throws InvalidStatisticsPeriod
     */
    public function assertEndsNoLaterThan(LocalDate $today): void
    {
        if ($this->to->isAfter($today)) {
            throw InvalidStatisticsPeriod::endsAfterToday($this->to->toString(), $today->toString());
        }
    }

    public function windowIn(DateTimeZone $zone): ReportingWindow
    {
        return new ReportingWindow(
            from: $this->from,
            to: $this->to,
            startsAt: $this->from->startsAtIn($zone),
            endsAt: $this->to->plusDays(1)->startsAtIn($zone),
        );
    }
}
