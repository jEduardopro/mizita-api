<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

enum PasswordStatus
{
    case Absent;
    case Temporary;
    case Chosen;

    public function acceptsTemporaryPassword(): bool
    {
        return $this !== self::Chosen;
    }
}
