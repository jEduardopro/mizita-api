<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use RuntimeException;

final class BusinessNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self("Business [{$id}] was not found.");
    }
}
