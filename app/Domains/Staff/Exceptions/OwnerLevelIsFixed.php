<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class OwnerLevelIsFixed extends DomainException implements DomainFailure
{
    public static function for(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] owns the business, and that level cannot change.");
    }

    public function errorCode(): string
    {
        return 'owner_level_is_fixed';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
