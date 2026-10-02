<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class BusinessOwnerDeactivated extends DomainException implements DomainFailure
{
    public static function forBusiness(string $businessId): self
    {
        return new self("The owner account of business [{$businessId}] is deactivated.");
    }

    public function errorCode(): string
    {
        return 'business_owner_deactivated';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
