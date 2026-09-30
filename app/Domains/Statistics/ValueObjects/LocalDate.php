<?php

declare(strict_types=1);

namespace App\Domains\Statistics\ValueObjects;

use App\Domains\Statistics\Exceptions\InvalidStatisticsPeriod;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final readonly class LocalDate
{
    public const FORMAT = 'Y-m-d';

    private const SHAPE = '/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})$/D';

    private const CALENDAR_ZONE = 'UTC';

    private const MIDNIGHT_FORMAT = '!Y-m-d';

    private const DAY_START = ' 00:00:00';

    private const PARTS_FORMAT = '%04d-%02d-%02d';

    private const FIRST_DAY = 1;

    private const FIRST_MONTH = 1;

    private const LAST_MONTH = 12;

    private function __construct(
        private DateTimeImmutable $day,
    ) {}

    /**
     * @throws InvalidStatisticsPeriod
     */
    public static function fromString(string $value): self
    {
        $candidate = trim($value);

        if (preg_match(self::SHAPE, $candidate, $matches) !== 1) {
            throw InvalidStatisticsPeriod::malformed($value);
        }

        if (! checkdate((int) $matches['month'], (int) $matches['day'], (int) $matches['year'])) {
            throw InvalidStatisticsPeriod::malformed($value);
        }

        return self::fromParts((int) $matches['year'], (int) $matches['month'], (int) $matches['day']);
    }

    public static function at(DateTimeImmutable $instant, DateTimeZone $zone): self
    {
        $local = $instant->setTimezone($zone);

        return self::fromParts((int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j'));
    }

    public function toString(): string
    {
        return $this->day->format(self::FORMAT);
    }

    public function firstOfMonth(): self
    {
        return self::fromParts($this->year(), $this->month(), self::FIRST_DAY);
    }

    public function sameDayOfPreviousMonth(): self
    {
        $year = $this->month() === self::FIRST_MONTH ? $this->year() - 1 : $this->year();
        $month = $this->month() === self::FIRST_MONTH ? self::LAST_MONTH : $this->month() - 1;
        $lastDay = self::daysInMonth($year, $month);

        return self::fromParts($year, $month, min($this->dayOfMonth(), $lastDay));
    }

    public function plusDays(int $days): self
    {
        return new self($this->day->add(new DateInterval('P'.$days.'D')));
    }

    public function minusDays(int $days): self
    {
        return new self($this->day->sub(new DateInterval('P'.$days.'D')));
    }

    public function isAfter(self $other): bool
    {
        return $this->day > $other->day;
    }

    public function daysThrough(self $end): int
    {
        return (int) $this->day->diff($end->day)->days + 1;
    }

    public function startsAtIn(DateTimeZone $zone): DateTimeImmutable
    {
        return (new DateTimeImmutable($this->toString().self::DAY_START, $zone))
            ->setTimezone(new DateTimeZone(self::CALENDAR_ZONE));
    }

    private static function fromParts(int $year, int $month, int $day): self
    {
        $formatted = sprintf(self::PARTS_FORMAT, $year, $month, $day);

        return new self(self::midnightOf($formatted));
    }

    private static function midnightOf(string $formatted): DateTimeImmutable
    {
        $midnight = DateTimeImmutable::createFromFormat(
            self::MIDNIGHT_FORMAT,
            $formatted,
            new DateTimeZone(self::CALENDAR_ZONE),
        );

        if ($midnight === false) {
            throw InvalidStatisticsPeriod::malformed($formatted);
        }

        return $midnight;
    }

    private static function daysInMonth(int $year, int $month): int
    {
        $formatted = sprintf(self::PARTS_FORMAT, $year, $month, self::FIRST_DAY);

        return (int) self::midnightOf($formatted)->format('t');
    }

    private function year(): int
    {
        return (int) $this->day->format('Y');
    }

    private function month(): int
    {
        return (int) $this->day->format('n');
    }

    private function dayOfMonth(): int
    {
        return (int) $this->day->format('j');
    }
}
