<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use RuntimeException;

final class AccountNotFound extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self("Account [{$id}] was not found.");
    }
}
