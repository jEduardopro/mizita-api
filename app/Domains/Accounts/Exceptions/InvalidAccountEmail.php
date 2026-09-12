<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use DomainException;

final class InvalidAccountEmail extends DomainException
{
    public static function empty(): self
    {
        return new self('An account email cannot be empty.');
    }

    public static function malformed(string $email): self
    {
        return new self("[{$email}] is not a valid email address.");
    }
}
