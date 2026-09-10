<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use DomainException;

final class InvalidBusinessName extends DomainException
{
    public static function empty(): self
    {
        return new self('A business name cannot be empty.');
    }
}
