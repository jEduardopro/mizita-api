<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use DomainException;

final class InvalidCustomerName extends DomainException
{
    public static function empty(): self
    {
        return new self('A customer name cannot be empty.');
    }
}
