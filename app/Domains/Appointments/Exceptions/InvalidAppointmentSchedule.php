<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidAppointmentSchedule extends DomainException implements DomainFailure
{
    public static function malformed(): self
    {
        return new self('The appointment time offered is not a readable instant.');
    }

    public static function inverted(): self
    {
        return new self('An appointment has to end after it starts.');
    }

    public static function tooLong(int $maximumMinutes): self
    {
        return new self("An appointment may not run past {$maximumMinutes} minutes.");
    }

    public static function tooShort(): self
    {
        return new self('An appointment has to last at least one minute.');
    }

    public function errorCode(): string
    {
        return 'invalid_appointment_schedule';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
