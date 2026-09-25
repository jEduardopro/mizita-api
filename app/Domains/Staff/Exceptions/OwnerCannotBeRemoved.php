<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class OwnerCannotBeRemoved extends DomainException implements DomainFailure
{
    public static function for(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] owns the business and cannot be removed from it.");
    }

    public function errorCode(): string
    {
        return 'owner_cannot_be_removed';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
