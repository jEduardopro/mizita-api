<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidAppointmentNotes extends DomainException implements DomainFailure
{
    public static function malformed(): self
    {
        return new self('The notes offered are not readable text.');
    }

    public static function tooLong(int $maximum): self
    {
        return new self("Appointment notes may not run past {$maximum} characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_appointment_notes';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
