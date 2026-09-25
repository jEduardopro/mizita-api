<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AppointmentStaffNotPermitted extends DomainException implements DomainFailure
{
    public static function withId(string $staffMemberId): self
    {
        return new self("The caller may not book or move appointments on team member [{$staffMemberId}]'s calendar.");
    }

    public function errorCode(): string
    {
        return 'appointment_staff_not_permitted';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
