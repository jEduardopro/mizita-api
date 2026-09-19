<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

final readonly class GuestBookingConfirmationData
{
    public function __construct(
        public GuestBookingData $booking,
        public string $manageToken,
    ) {}
}
