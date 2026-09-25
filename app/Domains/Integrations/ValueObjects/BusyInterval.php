<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

use App\Domains\Integrations\Exceptions\InvalidExternalCalendarEvent;
use DateTimeImmutable;
use DateTimeZone;

final readonly class BusyInterval
{
    private const STORAGE_TIMEZONE = 'UTC';

    public DateTimeImmutable $startsAt;

    public DateTimeImmutable $endsAt;

    /**
     * @throws InvalidExternalCalendarEvent
     */
    public function __construct(DateTimeImmutable $startsAt, DateTimeImmutable $endsAt)
    {
        if ($endsAt <= $startsAt) {
            throw InvalidExternalCalendarEvent::endsBeforeItStarts();
        }

        $utc = new DateTimeZone(self::STORAGE_TIMEZONE);

        $this->startsAt = $startsAt->setTimezone($utc);
        $this->endsAt = $endsAt->setTimezone($utc);
    }
}
