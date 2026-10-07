<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Http\Resources;

use App\Domains\Notifications\Application\Dtos\NotifiedAppointmentData;
use App\Domains\Notifications\Application\Dtos\NotifiedCustomerData;
use App\Domains\Notifications\Application\Dtos\NotifiedStaffMemberData;
use App\Domains\Notifications\Application\Dtos\StaffNotificationData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StaffNotificationData $resource
 */
final class StaffNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'type' => $this->resource->type,
            'read_at' => $this->resource->readAt?->format(DATE_ATOM),
            'created_at' => $this->resource->createdAt->format(DATE_ATOM),
            'can_mark_as_read' => $this->resource->canMarkAsRead,
            'recipient' => [
                'id' => $this->resource->recipient->id,
                'name' => $this->resource->recipient->name,
            ],
            'appointment' => self::appointmentOf($this->resource->appointment),
            'customer' => self::customerOf($this->resource->customer),
            'staff_member' => self::staffMemberOf($this->resource->staffMember),
        ];
    }

    /**
     * @return array{id: string, starts_at: string, ends_at: string, service_name: string, reference_code: ?string}|null
     */
    private static function appointmentOf(?NotifiedAppointmentData $appointment): ?array
    {
        if ($appointment === null) {
            return null;
        }

        return [
            'id' => $appointment->id,
            'starts_at' => $appointment->startsAt->format(DATE_ATOM),
            'ends_at' => $appointment->endsAt->format(DATE_ATOM),
            'service_name' => $appointment->serviceName,
            'reference_code' => $appointment->referenceCode,
        ];
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private static function customerOf(?NotifiedCustomerData $customer): ?array
    {
        if ($customer === null) {
            return null;
        }

        return [
            'id' => $customer->id,
            'name' => $customer->name,
        ];
    }

    /**
     * @return array{id: string, name: string}|null
     */
    private static function staffMemberOf(?NotifiedStaffMemberData $staffMember): ?array
    {
        if ($staffMember === null) {
            return null;
        }

        return [
            'id' => $staffMember->id,
            'name' => $staffMember->name,
        ];
    }
}
