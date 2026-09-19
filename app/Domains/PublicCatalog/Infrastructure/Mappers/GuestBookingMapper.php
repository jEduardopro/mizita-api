<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Mappers;

use App\Domains\Appointments\Application\Dtos\GuestBookingData;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingStatus;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;

final class GuestBookingMapper
{
    public function toPublicBooking(GuestBookingData $booking): PublicGuestBooking
    {
        return new PublicGuestBooking(
            referenceCode: $booking->referenceCode,
            customerName: $booking->customerName,
            serviceName: $booking->serviceName,
            staffMemberName: $booking->staffMemberName,
            startsAt: $booking->startsAt,
            endsAt: $booking->endsAt,
            durationMinutes: $booking->durationMinutes,
            status: PublicBookingStatus::from($booking->status->value),
            cancelledAt: $booking->cancelledAt,
            cancellationWindowMinutes: $booking->cancellationWindowMinutes,
            changeable: $booking->changeable,
        );
    }
}
