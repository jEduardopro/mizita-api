<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Eloquent\Mappers;

use App\Domains\Integrations\Entities\CalendarEventLink;
use App\Domains\Integrations\Infrastructure\Eloquent\Models\CalendarEventLinkModel;

final class CalendarEventLinkMapper
{
    public function toEntity(CalendarEventLinkModel $model, string $businessId): CalendarEventLink
    {
        return CalendarEventLink::restore(
            id: $model->uuid,
            businessId: $businessId,
            connectionId: $model->connection->uuid,
            appointmentId: $model->appointment->uuid,
            externalEventId: $model->external_event_id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(CalendarEventLink $link, int $businessKey, int $connectionKey, int $appointmentKey): array
    {
        return [
            'uuid' => $link->id,
            'business_id' => $businessKey,
            'calendar_connection_id' => $connectionKey,
            'appointment_id' => $appointmentKey,
            'external_event_id' => $link->externalEventId(),
        ];
    }
}
