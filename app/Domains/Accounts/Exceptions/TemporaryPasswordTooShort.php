<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use LogicException;

final class TemporaryPasswordTooShort extends LogicException
{
    public static function belowMinimum(int $minimumLength): self
    {
        return new self("A temporary password takes at least [{$minimumLength}] characters.");
    }
}
