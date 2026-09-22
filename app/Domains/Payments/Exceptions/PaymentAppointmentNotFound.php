<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentAppointmentNotFound extends DomainException implements DomainFailure
{
    public static function withId(string $appointmentId): self
    {
        return new self("Appointment [{$appointmentId}] was not found for this business.");
    }

    public static function malformed(string $value): self
    {
        return new self("[{$value}] is not a well formed appointment identifier.");
    }

    public function errorCode(): string
    {
        return 'payment_appointment_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
