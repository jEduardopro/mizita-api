<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class CalendarAppointmentNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $appointmentId): self
    {
        return new self("Appointment [{$appointmentId}] was not found.");
    }

    public function errorCode(): string
    {
        return 'appointment_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
