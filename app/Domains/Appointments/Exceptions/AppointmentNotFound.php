<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class AppointmentNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Appointment [{$id}] was not found.");
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
