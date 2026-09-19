<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Resources;

use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PublicGuestBooking $resource
 */
final class PublicBookingResource extends JsonResource
{
    /**
     * @return array{reference_code: string, customer_name: string, service_name: string, staff_member_name: string, starts_at: string, ends_at: string, duration_minutes: int, status: string, cancelled_at: string|null, cancellation_window_minutes: int|null, changeable: bool}
     */
    public function toArray(Request $request): array
    {
        $booking = $this->resource;

        return [
            'reference_code' => $booking->referenceCode,
            'customer_name' => $booking->customerName,
            'service_name' => $booking->serviceName,
            'staff_member_name' => $booking->staffMemberName,
            'starts_at' => $booking->startsAt->format(DATE_ATOM),
            'ends_at' => $booking->endsAt->format(DATE_ATOM),
            'duration_minutes' => $booking->durationMinutes,
            'status' => $booking->status->value,
            'cancelled_at' => $booking->cancelledAt?->format(DATE_ATOM),
            'cancellation_window_minutes' => $booking->cancellationWindowMinutes,
            'changeable' => $booking->changeable,
        ];
    }
}
