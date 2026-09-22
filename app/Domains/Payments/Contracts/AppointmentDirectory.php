<?php

declare(strict_types=1);

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\ValueObjects\AppointmentSnapshot;

interface AppointmentDirectory
{
    /**
     * @throws PaymentAppointmentNotFound
     */
    public function describe(string $businessId, string $appointmentId): AppointmentSnapshot;
}
