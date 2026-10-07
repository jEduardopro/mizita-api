<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Eloquent\Mappers;

use App\Domains\Notifications\Entities\NotificationEvent;

final class NotificationEventMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toAttributes(NotificationEvent $event, int $businessKey, int $subjectKey): array
    {
        return [
            'uuid' => $event->id,
            'business_id' => $businessKey,
            'type' => $event->type,
            'subject_type' => $event->subject->type,
            'subject_id' => $subjectKey,
            'payload' => $event->payload->toArray(),
            'idempotency_key' => $event->idempotencyKey,
            'occurred_at' => $event->occurredAt,
            'created_at' => $event->occurredAt,
            'updated_at' => $event->occurredAt,
        ];
    }
}
