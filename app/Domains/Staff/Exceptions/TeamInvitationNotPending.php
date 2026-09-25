<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class TeamInvitationNotPending extends DomainException implements DomainFailure
{
    public static function for(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] has no invitation waiting to be accepted.");
    }

    public function errorCode(): string
    {
        return 'team_invitation_not_pending';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
