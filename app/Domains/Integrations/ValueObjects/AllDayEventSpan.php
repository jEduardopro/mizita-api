<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

use App\Domains\Integrations\Exceptions\InvalidExternalCalendarEvent;
use DateTimeImmutable;
use DateTimeZone;

final readonly class AllDayEventSpan implements EventSpan
{
    private const DATE_FORMAT = 'Y-m-d';

    private const MIDNIGHT_FORMAT = '!Y-m-d';

    /**
     * @throws InvalidExternalCalendarEvent
     */
    public function __construct(
        public string $firstDate,
        public string $dayAfterLastDate,
    ) {
        self::assertCalendarDate($firstDate);
        self::assertCalendarDate($dayAfterLastDate);

        if ($dayAfterLastDate <= $firstDate) {
            throw InvalidExternalCalendarEvent::endsBeforeItStarts();
        }
    }

    public function intervalIn(DateTimeZone $businessZone): BusyInterval
    {
        return new BusyInterval(
            self::localMidnightOf($this->firstDate, $businessZone),
            self::localMidnightOf($this->dayAfterLastDate, $businessZone),
        );
    }

    private static function localMidnightOf(string $date, DateTimeZone $businessZone): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(self::MIDNIGHT_FORMAT, $date, $businessZone)
            ?: throw InvalidExternalCalendarEvent::malformedDate($date);
    }

    /**
     * @throws InvalidExternalCalendarEvent
     */
    private static function assertCalendarDate(string $date): void
    {
        $parsed = DateTimeImmutable::createFromFormat(self::MIDNIGHT_FORMAT, $date);

        if ($parsed === false || $parsed->format(self::DATE_FORMAT) !== $date) {
            throw InvalidExternalCalendarEvent::malformedDate($date);
        }
    }
}
