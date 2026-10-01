<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidBusinessSelection extends DomainException implements DomainFailure
{
    public static function missing(): self
    {
        return new self('No business was named to switch to.');
    }

    public static function malformed(string $businessId): self
    {
        return new self("[{$businessId}] is not a business identifier.");
    }

    public function errorCode(): string
    {
        return 'invalid_business_selection';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
