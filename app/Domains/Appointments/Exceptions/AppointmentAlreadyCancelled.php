<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AppointmentAlreadyCancelled extends DomainException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Appointment [{$id}] was already cancelled.");
    }

    public function errorCode(): string
    {
        return 'appointment_already_cancelled';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
