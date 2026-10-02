<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class ImpersonationConfinedToBusiness extends DomainException implements DomainFailure
{
    public static function outside(string $impersonatedBusinessId, string $requestedBusinessId): self
    {
        return new self(
            "The impersonation is confined to business [{$impersonatedBusinessId}] and cannot operate [{$requestedBusinessId}].",
        );
    }

    public function errorCode(): string
    {
        return 'business_not_accessible';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
