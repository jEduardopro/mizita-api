<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidTeamLevel extends DomainException implements DomainFailure
{
    public static function unknown(string $level): self
    {
        return new self("[{$level}] is not a permission level a team member can be given.");
    }

    public static function ownerNotAssignable(): self
    {
        return new self('The owner level belongs to whoever registered the business and cannot be given.');
    }

    public function errorCode(): string
    {
        return 'invalid_team_level';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
