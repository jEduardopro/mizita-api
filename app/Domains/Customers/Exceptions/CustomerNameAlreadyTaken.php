<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use DomainException;

final class CustomerNameAlreadyTaken extends DomainException
{
    public static function for(string $name): self
    {
        return new self("A customer named [{$name}] already exists.");
    }
}
