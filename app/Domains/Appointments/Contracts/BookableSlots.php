<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use DateTimeImmutable;

interface BookableSlots
{
    /**
     * @throws AppointmentServiceNotFound
     * @throws AppointmentStaffNotFound
     */
    public function isBookable(
        string $businessId,
        string $serviceId,
        string $staffMemberId,
        DateTimeImmutable $startsAt,
        ?string $excludingAppointmentId = null,
    ): bool;
}
