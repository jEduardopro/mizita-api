<?php

declare(strict_types=1);

namespace App\Domains\Industries\Exceptions;

use DomainException;

final class IndustryAlreadyInactive extends DomainException
{
    public static function for(string $id): self
    {
        return new self("Industry [{$id}] is already inactive.");
    }
}
