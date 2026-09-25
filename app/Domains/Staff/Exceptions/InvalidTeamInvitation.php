<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidTeamInvitation extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('An invitation needs at least one team member.');
    }

    public static function tooManyMembers(int $maximum): self
    {
        return new self("An invitation takes up to [{$maximum}] team members at once.");
    }

    public function errorCode(): string
    {
        return 'invalid_team_invitation';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
