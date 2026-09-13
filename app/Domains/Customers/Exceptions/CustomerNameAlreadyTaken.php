<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class CustomerNameAlreadyTaken extends DomainException implements DomainFailure
{
    public static function for(string $name): self
    {
        return new self("A customer named [{$name}] already exists.");
    }

    public function errorCode(): string
    {
        return 'customer_name_taken';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
