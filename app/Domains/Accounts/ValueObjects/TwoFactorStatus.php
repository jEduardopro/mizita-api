<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

enum TwoFactorStatus: string
{
    case Disabled = 'disabled';
    case Pending = 'pending';
    case Enabled = 'enabled';

    public function requiresSecondFactor(): bool
    {
        return $this === self::Enabled;
    }
}
