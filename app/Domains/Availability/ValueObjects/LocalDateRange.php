<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

use App\Domains\Availability\Exceptions\AvailabilityRangeTooWide;
use App\Domains\Availability\Exceptions\InvalidAvailabilityRange;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final readonly class LocalDateRange
{
    public const MAXIMUM_DAYS = 62;

    private const FORMAT = 'Y-m-d';

    private const SHAPE = '/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})$/D';

    private const ONE_DAY = 'P1D';

    private const DAY_START = ' 00:00:00';

    private const CALENDAR_ZONE = 'UTC';

    private function __construct(
        public string $from,
        public string $to,
    ) {}

    /**
     * @throws AvailabilityRangeTooWide
     * @throws InvalidAvailabilityRange
     */
    public static function between(string $from, string $to): self
    {
        $start = self::parse($from);
        $end = self::parse($to);

        if ($start > $end) {
            throw InvalidAvailabilityRange::inverted($from, $to);
        }

        $days = self::daysBetween($start, $end);

        if ($days > self::MAXIMUM_DAYS) {
            throw AvailabilityRangeTooWide::spanning($days, self::MAXIMUM_DAYS);
        }

        return new self($start->format(self::FORMAT), $end->format(self::FORMAT));
    }

    public function startsAtIn(DateTimeZone $zone): DateTimeImmutable
    {
        return new DateTimeImmutable($this->from.self::DAY_START, $zone);
    }

    public function endsAtIn(DateTimeZone $zone): DateTimeImmutable
    {
        return (new DateTimeImmutable($this->to.self::DAY_START, $zone))
            ->add(new DateInterval(self::ONE_DAY));
    }

    /**
     * @return list<string>
     */
    public function dates(): array
    {
        $dates = [];
        $cursor = self::parse($this->from);
        $end = self::parse($this->to);
        $step = new DateInterval(self::ONE_DAY);

        while ($cursor <= $end) {
            $dates[] = $cursor->format(self::FORMAT);
            $cursor = $cursor->add($step);
        }

        return $dates;
    }

    /**
     * @throws InvalidAvailabilityRange
     */
    private static function parse(string $value): DateTimeImmutable
    {
        $candidate = trim($value);

        if (preg_match(self::SHAPE, $candidate, $matches) !== 1) {
            throw InvalidAvailabilityRange::malformed($value);
        }

        if (! checkdate((int) $matches['month'], (int) $matches['day'], (int) $matches['year'])) {
            throw InvalidAvailabilityRange::malformed($value);
        }

        return new DateTimeImmutable($candidate, new DateTimeZone(self::CALENDAR_ZONE));
    }

    private static function daysBetween(DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        return (int) $start->diff($end)->days + 1;
    }
}
