<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Staff\Contracts\UpcomingAppointments;
use DateTimeImmutable;

final class AppointmentsUpcomingAppointments implements UpcomingAppointments
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
    ) {}

    public function existFor(string $businessId, string $staffMemberId, DateTimeImmutable $now): bool
    {
        return $this->appointments->hasUpcomingForStaffMember($businessId, $staffMemberId, $now);
    }
}
