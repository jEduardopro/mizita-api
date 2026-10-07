<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Eloquent\Mappers;

use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\StaffNotificationModel;
use DateTimeImmutable;

final class StaffNotificationMapper
{
    public function toEntity(StaffNotificationModel $model, string $businessId): StaffNotification
    {
        /** @var ?DateTimeImmutable $readAt */
        $readAt = $model->read_at;

        /** @var DateTimeImmutable $createdAt */
        $createdAt = $model->created_at;

        return StaffNotification::restore(
            id: $model->uuid,
            businessId: $businessId,
            eventId: $model->event->uuid,
            recipientStaffMemberId: $model->recipient->uuid,
            collapseKey: $model->collapse_key,
            readAt: $readAt,
            createdAt: $createdAt,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(
        StaffNotification $notification,
        int $businessKey,
        int $eventKey,
        int $recipientKey,
    ): array {
        return [
            'uuid' => $notification->id,
            'business_id' => $businessKey,
            'notification_event_id' => $eventKey,
            'recipient_staff_member_id' => $recipientKey,
            'collapse_key' => $notification->collapseKey,
            'read_at' => $notification->readAt(),
            'created_at' => $notification->createdAt,
        ];
    }
}
