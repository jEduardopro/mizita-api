<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

use App\Domains\Integrations\Exceptions\InvalidExternalCalendarEvent;
use DateTimeImmutable;
use DateTimeZone;

final readonly class TimedEventSpan implements EventSpan
{
    /**
     * @throws InvalidExternalCalendarEvent
     */
    public function __construct(
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
    ) {
        if ($endsAt <= $startsAt) {
            throw InvalidExternalCalendarEvent::endsBeforeItStarts();
        }
    }

    public function intervalIn(DateTimeZone $businessZone): BusyInterval
    {
        return new BusyInterval($this->startsAt, $this->endsAt);
    }
}
