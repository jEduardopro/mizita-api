<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\AppointmentNotFound;
use App\Domains\Appointments\Exceptions\CalendarNotAccessible;
use App\Domains\Appointments\ValueObjects\Identifier;

final readonly class ShowAppointmentInput
{
    public function __construct(
        public string $appointmentId,
        public string $accountId,
    ) {}

    /**
     * @throws AppointmentNotFound
     * @throws CalendarNotAccessible
     */
    public function validate(): void
    {
        $this->validateAppointmentId();
        $this->validateAccountId();
    }

    private function validateAppointmentId(): void
    {
        if (! Identifier::isWellFormed($this->appointmentId)) {
            throw AppointmentNotFound::withId($this->appointmentId);
        }
    }

    private function validateAccountId(): void
    {
        if (! Identifier::isWellFormed($this->accountId)) {
            throw CalendarNotAccessible::forAccount($this->accountId);
        }
    }
}
