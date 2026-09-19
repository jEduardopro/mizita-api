<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Eloquent\Mappers;

use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Infrastructure\Eloquent\Models\AppointmentModel;
use App\Domains\Appointments\ValueObjects\AppointmentNotes;
use App\Domains\Appointments\ValueObjects\AppointmentSlot;
use App\Domains\Appointments\ValueObjects\ReferenceCode;
use DateTimeImmutable;

final class AppointmentMapper
{
    public function toEntity(AppointmentModel $model, string $businessId): Appointment
    {
        return Appointment::restore(
            id: $model->uuid,
            businessId: $businessId,
            customerId: $model->customer->uuid,
            serviceId: $model->service->uuid,
            staffMemberId: $model->staffMember->uuid,
            slot: AppointmentSlot::restore(
                DateTimeImmutable::createFromInterface($model->starts_at),
                DateTimeImmutable::createFromInterface($model->ends_at),
            ),
            notes: $model->notes === null ? null : AppointmentNotes::restore($model->notes),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            referenceCode: $model->reference_code === null ? null : ReferenceCode::restore($model->reference_code),
            manageTokenHash: $model->manage_token_hash,
            manageTokenExpiresAt: $model->manage_token_expires_at,
            cancelledAt: $model->cancelled_at,
            cancelledBy: $model->cancelled_by,
            source: $model->source,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(
        Appointment $appointment,
        int $businessKey,
        int $customerKey,
        int $serviceKey,
        int $staffMemberKey,
    ): array {
        return [
            'uuid' => $appointment->id,
            'business_id' => $businessKey,
            'customer_id' => $customerKey,
            'service_id' => $serviceKey,
            'staff_member_id' => $staffMemberKey,
            'starts_at' => $appointment->slot()->startsAt->format(DATE_ATOM),
            'ends_at' => $appointment->slot()->endsAt->format(DATE_ATOM),
            'notes' => $appointment->notes()?->value,
            'reference_code' => $appointment->referenceCode()?->value,
            'manage_token_hash' => $appointment->manageTokenHash(),
            'manage_token_expires_at' => $appointment->manageTokenExpiresAt()?->format(DATE_ATOM),
            'cancelled_at' => $appointment->cancelledAt()?->format(DATE_ATOM),
            'cancelled_by' => $appointment->cancelledBy(),
            'source' => $appointment->source(),
        ];
    }
}
