<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Http\Resources;

use App\Domains\Appointments\Application\Dtos\AppointmentCustomerData;
use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Dtos\AppointmentServiceData;
use App\Domains\Appointments\Application\Dtos\AppointmentStaffData;
use App\Domains\Appointments\ValueObjects\CustomerPhoneSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AppointmentData $resource
 */
final class AppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'customer' => self::describeCustomer($this->resource->customer),
            'service' => self::describeService($this->resource->service),
            'staff_member' => self::describeStaffMember($this->resource->staffMember),
            'starts_at' => $this->resource->startsAt->format(DATE_ATOM),
            'ends_at' => $this->resource->endsAt->format(DATE_ATOM),
            'duration_minutes' => $this->resource->durationMinutes,
            'notes' => $this->resource->notes,
            'created_at' => $this->resource->createdAt->format(DATE_ATOM),
            'status' => $this->resource->status->value,
            'cancelled_at' => $this->resource->cancelledAt?->format(DATE_ATOM),
            'cancelled_by' => $this->resource->cancelledBy?->value,
            'reference_code' => $this->resource->referenceCode,
            'payment_status' => $this->resource->paymentStatus?->value,
        ];
    }

    /**
     * @return array{id: string, name: string, email: string|null, phone: array{country_code: string, national_number: string}|null}
     */
    private static function describeCustomer(AppointmentCustomerData $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => self::describePhone($customer->phone),
        ];
    }

    /**
     * @return array{country_code: string, national_number: string}|null
     */
    private static function describePhone(?CustomerPhoneSnapshot $phone): ?array
    {
        if ($phone === null) {
            return null;
        }

        return [
            'country_code' => $phone->countryCode,
            'national_number' => $phone->nationalNumber,
        ];
    }

    /**
     * @return array{id: string, name: string, color: string, duration_minutes: int, buffer_minutes: int, price: string}
     */
    private static function describeService(AppointmentServiceData $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'color' => $service->color,
            'duration_minutes' => $service->durationMinutes,
            'buffer_minutes' => $service->bufferMinutes,
            'price' => $service->price,
        ];
    }

    /**
     * @return array{id: string, name: string}
     */
    private static function describeStaffMember(AppointmentStaffData $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
        ];
    }
}
