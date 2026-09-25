<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

use App\Domains\Integrations\Exceptions\InvalidExternalCalendarEvent;
use DateTimeZone;

final readonly class ExternalCalendarEvent
{
    public function __construct(
        public EventSpan $span,
        public ExternalEventOrigin $origin,
        public ExternalEventAvailability $availability,
    ) {}

    public function blocksTime(): bool
    {
        return $this->origin === ExternalEventOrigin::AddedByHand
            && $this->availability === ExternalEventAvailability::Busy;
    }

    /**
     * @throws InvalidExternalCalendarEvent
     */
    public function intervalIn(DateTimeZone $businessZone): BusyInterval
    {
        return $this->span->intervalIn($businessZone);
    }
}
