<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Services;

use App\Domains\Appointments\Application\Dtos\GuestBookingCredentials;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Exceptions\GuestBookingNotFound;
use DateTimeImmutable;

final class GuestBookingFinder
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
    ) {}

    /**
     * @throws GuestBookingNotFound
     */
    public function find(
        string $businessId,
        GuestBookingCredentials $credentials,
        DateTimeImmutable $now,
    ): Appointment {
        $appointment = $this->appointments->findByReferenceCode(
            $businessId,
            $credentials->toReferenceCode(),
        );

        if ($appointment === null) {
            throw GuestBookingNotFound::forCredentials();
        }

        if (! $appointment->hasValidManageToken($credentials->manageToken, $now)) {
            throw GuestBookingNotFound::forCredentials();
        }

        return $appointment;
    }
}
