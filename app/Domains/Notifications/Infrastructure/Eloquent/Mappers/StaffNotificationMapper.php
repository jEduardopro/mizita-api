<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Eloquent\Mappers;

use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Infrastructure\Eloquent\Models\StaffNotificationModel;
use App\Domains\Notifications\ValueObjects\NotificationType;
use DateTimeImmutable;

final class StaffNotificationMapper
{
    public function toEntity(StaffNotificationModel $model, string $businessId): StaffNotification
    {
        /** @var NotificationType $type */
        $type = $model->type;

        /** @var ?DateTimeImmutable $readAt */
        $readAt = $model->read_at;

        /** @var DateTimeImmutable $createdAt */
        $createdAt = $model->created_at;

        return StaffNotification::restore(
            id: $model->uuid,
            businessId: $businessId,
            recipientStaffMemberId: $model->recipient->uuid,
            type: $type,
            appointmentId: $model->appointment?->uuid,
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
        int $recipientKey,
        ?int $appointmentKey,
    ): array {
        return [
            'uuid' => $notification->id,
            'business_id' => $businessKey,
            'recipient_staff_member_id' => $recipientKey,
            'type' => $notification->type,
            'appointment_id' => $appointmentKey,
            'read_at' => $notification->readAt(),
            'created_at' => $notification->createdAt,
        ];
    }
}
