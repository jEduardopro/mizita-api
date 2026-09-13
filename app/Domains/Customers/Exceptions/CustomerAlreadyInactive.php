<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class CustomerAlreadyInactive extends DomainException implements DomainFailure
{
    public static function for(string $id): self
    {
        return new self("Customer [{$id}] is already inactive.");
    }

    public function errorCode(): string
    {
        return 'customer_already_inactive';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
