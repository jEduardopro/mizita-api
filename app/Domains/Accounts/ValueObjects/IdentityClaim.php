<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

enum IdentityClaim
{
    case ProvenOwnerKeptAccess;
    case UnprovenAccessRevoked;

    public function revokedPriorAccess(): bool
    {
        return $this === self::UnprovenAccessRevoked;
    }
}
