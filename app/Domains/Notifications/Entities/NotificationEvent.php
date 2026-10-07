<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Entities;

use App\Domains\Notifications\ValueObjects\NotificationSubject;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Notifications\ValueObjects\Payloads\NotificationPayload;
use DateTimeImmutable;

final readonly class NotificationEvent
{
    private function __construct(
        public string $id,
        public string $businessId,
        public NotificationType $type,
        public NotificationSubject $subject,
        public NotificationPayload $payload,
        public string $idempotencyKey,
        public DateTimeImmutable $occurredAt,
    ) {}

    public static function record(
        string $id,
        string $businessId,
        NotificationPayload $payload,
        DateTimeImmutable $occurredAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            type: $payload->type(),
            subject: $payload->subject(),
            payload: $payload,
            idempotencyKey: $payload->idempotencyKey($id),
            occurredAt: $occurredAt,
        );
    }

    public function deliverTo(string $deliveryId, string $recipientStaffMemberId): StaffNotification
    {
        return StaffNotification::create(
            id: $deliveryId,
            businessId: $this->businessId,
            eventId: $this->id,
            recipientStaffMemberId: $recipientStaffMemberId,
            collapseKey: $this->payload->collapseKey($this->id),
            now: $this->occurredAt,
        );
    }
}
