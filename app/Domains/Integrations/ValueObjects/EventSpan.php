<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

use App\Domains\Integrations\Exceptions\InvalidExternalCalendarEvent;
use DateTimeZone;

interface EventSpan
{
    /**
     * @throws InvalidExternalCalendarEvent
     */
    public function intervalIn(DateTimeZone $businessZone): BusyInterval;
}
