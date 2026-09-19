<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AppointmentAlreadyStarted extends DomainException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Appointment [{$id}] has already started.");
    }

    public function errorCode(): string
    {
        return 'appointment_already_started';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
