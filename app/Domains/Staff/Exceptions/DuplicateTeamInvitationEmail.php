<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class DuplicateTeamInvitationEmail extends DomainException implements DomainFailure
{
    public static function for(string $email): self
    {
        return new self("[{$email}] appears more than once in the same invitation.");
    }

    public function errorCode(): string
    {
        return 'duplicate_team_invitation_email';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
