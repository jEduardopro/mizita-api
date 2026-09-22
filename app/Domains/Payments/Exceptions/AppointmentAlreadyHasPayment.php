<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class AppointmentAlreadyHasPayment extends DomainException implements DomainFailure
{
    public static function forAppointment(string $appointmentId, ?Throwable $previous = null): self
    {
        return new self("Appointment [{$appointmentId}] already has a payment.", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'appointment_already_has_payment';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
