<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class BusinessHasNoOwner extends DomainException implements DomainFailure
{
    public static function forBusiness(string $businessId): self
    {
        return new self("Business [{$businessId}] has no owner account to impersonate.");
    }

    public function errorCode(): string
    {
        return 'business_has_no_owner';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
