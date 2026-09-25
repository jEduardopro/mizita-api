<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class BusinessAlreadyClosed extends DomainException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Business [{$id}] is already closed.");
    }

    public function errorCode(): string
    {
        return 'business_already_closed';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
