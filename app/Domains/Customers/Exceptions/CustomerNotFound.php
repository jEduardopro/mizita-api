<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use RuntimeException;

final class CustomerNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self("Customer [{$id}] was not found.");
    }
}
