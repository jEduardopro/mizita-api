<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class ImpersonatedBusinessNotFound extends DomainException implements DomainFailure
{
    public static function withId(string $businessId): self
    {
        return new self("No business [{$businessId}] can be impersonated.");
    }

    public function errorCode(): string
    {
        return 'impersonated_business_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
