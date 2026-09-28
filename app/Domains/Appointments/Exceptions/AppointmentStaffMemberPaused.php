<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class AppointmentStaffMemberPaused extends RuntimeException implements DomainFailure
{
    public static function withId(string $staffMemberId): self
    {
        return new self("Team member [{$staffMemberId}] is paused on the current plan and cannot take new appointments.");
    }

    public function errorCode(): string
    {
        return 'staff_member_paused';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
