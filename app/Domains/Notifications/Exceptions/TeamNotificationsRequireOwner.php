<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class TeamNotificationsRequireOwner extends DomainException implements DomainFailure
{
    public static function forStaffMember(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] is not the owner, so it cannot read the whole team's notifications.");
    }

    public function errorCode(): string
    {
        return 'team_notifications_require_owner';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
