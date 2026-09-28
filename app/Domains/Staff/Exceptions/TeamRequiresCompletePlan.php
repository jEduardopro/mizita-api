<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class TeamRequiresCompletePlan extends DomainException implements DomainFailure
{
    public static function for(string $businessId): self
    {
        return new self("Business [{$businessId}] needs the complete plan to manage a team.");
    }

    public function errorCode(): string
    {
        return 'team_requires_complete_plan';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
