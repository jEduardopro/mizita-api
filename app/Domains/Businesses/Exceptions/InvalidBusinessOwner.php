<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidBusinessOwner extends DomainException implements DomainFailure
{
    public static function missing(): self
    {
        return new self('A business cannot be onboarded without an owner account.');
    }

    public function errorCode(): string
    {
        return 'invalid_business_owner';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
