<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DateTimeImmutable;
use DomainException;

final class AppointmentSlotNotBookable extends DomainException implements DomainFailure
{
    public static function startingAt(DateTimeImmutable $startsAt): self
    {
        return new self("No bookable slot starts at [{$startsAt->format(DATE_ATOM)}].");
    }

    public function errorCode(): string
    {
        return 'appointment_slot_not_bookable';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
