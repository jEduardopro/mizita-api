<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\AppointmentNotFound;
use App\Domains\Appointments\ValueObjects\Identifier;

final readonly class DeleteAppointmentInput
{
    public function __construct(
        public string $appointmentId,
    ) {}

    /**
     * @throws AppointmentNotFound
     */
    public function validate(): void
    {
        $this->validateAppointmentId();
    }

    private function validateAppointmentId(): void
    {
        if (! Identifier::isWellFormed($this->appointmentId)) {
            throw AppointmentNotFound::withId($this->appointmentId);
        }
    }
}
