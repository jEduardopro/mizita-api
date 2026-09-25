<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\Entities\CalendarEventLink;

interface CalendarEventLinkRepository
{
    /**
     * @return list<CalendarEventLink>
     */
    public function forAppointment(string $businessId, string $appointmentId): array;

    /**
     * @return list<string>
     */
    public function appointmentIdsLinkedTo(string $businessId, string $connectionId): array;

    public function save(CalendarEventLink $link): void;

    public function delete(string $businessId, string $id): void;

    public function deleteForConnection(string $businessId, string $connectionId): void;
}
