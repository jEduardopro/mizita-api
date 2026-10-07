<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class NotifiedAppointmentNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Appointment [{$id}] to notify about was not found.");
    }

    public function errorCode(): string
    {
        return 'notified_appointment_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
