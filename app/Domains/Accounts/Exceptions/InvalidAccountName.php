<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use DomainException;

final class InvalidAccountName extends DomainException
{
    public static function empty(): self
    {
        return new self('An account name cannot be empty.');
    }
}
