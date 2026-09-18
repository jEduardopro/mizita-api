<?php

declare(strict_types=1);

namespace App\Domains\Appointments\ValueObjects;

use App\Domains\Appointments\Exceptions\InvalidCalendarRange;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

final readonly class CalendarRange
{
    public const MAXIMUM_SPAN_DAYS = 62;

    private const PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/D';

    private const STORAGE_TIMEZONE = 'UTC';

    private const SECONDS_PER_DAY = 86400;

    private function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
    ) {}

    /**
     * @throws InvalidCalendarRange
     */
    public static function between(DateTimeImmutable $from, DateTimeImmutable $to): self
    {
        $seconds = $to->getTimestamp() - $from->getTimestamp();

        if ($seconds <= 0) {
            throw InvalidCalendarRange::inverted();
        }

        if ($seconds > self::MAXIMUM_SPAN_DAYS * self::SECONDS_PER_DAY) {
            throw InvalidCalendarRange::tooWide(self::MAXIMUM_SPAN_DAYS);
        }

        return new self($from, $to);
    }

    /**
     * @throws InvalidCalendarRange
     */
    public static function fromStrings(string $from, string $to): self
    {
        return self::between(self::instantFrom($from), self::instantFrom($to));
    }

    /**
     * @throws InvalidCalendarRange
     */
    private static function instantFrom(string $value): DateTimeImmutable
    {
        $instant = trim($value);

        if (preg_match(self::PATTERN, $instant) !== 1) {
            throw InvalidCalendarRange::malformed();
        }

        try {
            $parsed = new DateTimeImmutable($instant);
        } catch (Exception $unreadable) {
            throw InvalidCalendarRange::malformed();
        }

        return $parsed->setTimezone(new DateTimeZone(self::STORAGE_TIMEZONE));
    }
}
