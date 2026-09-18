<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class AppointmentOverlaps extends DomainException implements DomainFailure
{
    public static function withAnotherBooking(?Throwable $previous = null): self
    {
        return new self('That time slot overlaps an appointment already booked for this team member.', 0, $previous);
    }

    public function errorCode(): string
    {
        return 'appointment_overlap';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
