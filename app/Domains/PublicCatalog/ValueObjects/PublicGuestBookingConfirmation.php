<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicGuestBookingConfirmation
{
    public function __construct(
        public PublicGuestBooking $booking,
        public string $manageToken,
    ) {}
}
