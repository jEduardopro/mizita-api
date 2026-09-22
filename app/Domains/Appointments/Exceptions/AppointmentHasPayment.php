<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class AppointmentHasPayment extends DomainException implements DomainFailure
{
    public static function withId(string $appointmentId, ?Throwable $previous = null): self
    {
        return new self(sprintf('Appointment %s has a payment recorded and cannot be deleted.', $appointmentId), 0, $previous);
    }

    public function errorCode(): string
    {
        return 'appointment_has_payment';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
