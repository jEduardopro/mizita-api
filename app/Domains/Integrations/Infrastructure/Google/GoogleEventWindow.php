<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;

final readonly class GoogleEventWindow
{
    private const EARLIEST_UTC_OFFSET = '+14:00';

    private const LATEST_UTC_OFFSET = '-12:00';

    private const CALENDAR_DATE_AT_MIDNIGHT = '!Y-m-d';

    public function __construct(
        private DateTimeImmutable $from,
        private DateTimeImmutable $to,
    ) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public function mayOverlap(array $item): bool
    {
        try {
            $startsAt = self::boundaryOf($item['start'] ?? null, self::EARLIEST_UTC_OFFSET);
            $endsAt = self::boundaryOf($item['end'] ?? null, self::LATEST_UTC_OFFSET);
        } catch (DateMalformedStringException) {
            return true;
        }

        return $startsAt < $this->to && $endsAt > $this->from;
    }

    /**
     * @throws DateMalformedStringException
     */
    private static function boundaryOf(mixed $boundary, string $allDayOffset): DateTimeImmutable
    {
        if (! is_array($boundary)) {
            throw new DateMalformedStringException('Google event boundary is missing.');
        }

        if (is_string($boundary['dateTime'] ?? null)) {
            return new DateTimeImmutable($boundary['dateTime']);
        }

        return self::allDayBoundary((string) ($boundary['date'] ?? ''), $allDayOffset);
    }

    /**
     * @throws DateMalformedStringException
     */
    private static function allDayBoundary(string $date, string $offset): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::CALENDAR_DATE_AT_MIDNIGHT, $date, new DateTimeZone($offset))
            ?: throw new DateMalformedStringException("Google all-day date [{$date}] is malformed.");
    }
}
