<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Exceptions\AppointmentNotFound;
use App\Domains\Payments\Contracts\AppointmentDirectory;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\ValueObjects\AppointmentSnapshot;

final class AppointmentsAppointmentDirectory implements AppointmentDirectory
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
    ) {}

    public function describe(string $businessId, string $appointmentId): AppointmentSnapshot
    {
        try {
            $appointment = $this->appointments->findForBusiness($businessId, $appointmentId);
        } catch (AppointmentNotFound) {
            throw PaymentAppointmentNotFound::withId($appointmentId);
        }

        return new AppointmentSnapshot(
            id: $appointment->id,
            serviceId: $appointment->serviceId(),
            cancelled: $appointment->isCancelled(),
            staffMemberId: $appointment->staffMemberId(),
        );
    }
}
