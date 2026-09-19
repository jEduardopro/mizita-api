<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AppointmentChangesNotAllowed extends DomainException implements DomainFailure
{
    public static function byPolicy(): self
    {
        return new self('This business does not let customers change their bookings.');
    }

    public function errorCode(): string
    {
        return 'appointment_changes_not_allowed';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
