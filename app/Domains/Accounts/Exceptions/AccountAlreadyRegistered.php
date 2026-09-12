<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use DomainException;
use Throwable;

/**
 * Another request registered this address first.
 *
 * This is the storage layer's uniqueness rule reaching the domain in domain
 * terms. It says nothing about what to do next: a caller racing itself should
 * adopt the winner's account, not report a conflict.
 */
final class AccountAlreadyRegistered extends DomainException
{
    public static function withEmail(string $email, ?Throwable $previous = null): self
    {
        return new self("An account already exists for [{$email}].", previous: $previous);
    }
}
