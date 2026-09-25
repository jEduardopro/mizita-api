<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Gateways;

use App\Domains\Accounts\Contracts\UpcomingBookings;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Shared\Contracts\Clock;

final class AppointmentsUpcomingBookings implements UpcomingBookings
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly Clock $clock,
    ) {}

    public function countForBusiness(string $businessId): int
    {
        return $this->appointments->countUpcomingForBusiness($businessId, $this->clock->now());
    }
}
