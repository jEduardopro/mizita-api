<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

final readonly class BookedAppointment
{
    public function __construct(
        public string $appointmentId,
        public string $businessId,
        public string $staffMemberId,
    ) {}
}
