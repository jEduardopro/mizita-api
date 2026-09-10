<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use DomainException;

final class BusinessSlugAlreadyTaken extends DomainException
{
    public static function for(string $value): self
    {
        return new self("A business with slug [{$value}] already exists.");
    }
}
