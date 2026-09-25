<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\Exceptions\ExternalCalendarUnavailable;
use App\Domains\Integrations\ValueObjects\ExternalCalendarEvent;
use DateTimeImmutable;

interface CalendarEventFeed
{
    /**
     * @return list<ExternalCalendarEvent>
     *
     * @throws CalendarAuthorizationRevoked
     * @throws ExternalCalendarUnavailable
     */
    public function eventsBetween(CalendarConnection $connection, DateTimeImmutable $from, DateTimeImmutable $to): array;
}
