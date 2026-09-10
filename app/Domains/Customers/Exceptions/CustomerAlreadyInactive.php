<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use DomainException;

final class CustomerAlreadyInactive extends DomainException
{
    public static function for(string $id): self
    {
        return new self("Customer [{$id}] is already inactive.");
    }
}
