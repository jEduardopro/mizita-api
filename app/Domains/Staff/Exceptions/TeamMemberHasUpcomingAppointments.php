<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class TeamMemberHasUpcomingAppointments extends DomainException implements DomainFailure
{
    public static function for(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] still has upcoming appointments.");
    }

    public function errorCode(): string
    {
        return 'team_member_has_upcoming_appointments';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
