<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

enum AccessTransition
{
    case Granted;

    case Revoked;

    case Unchanged;

    public static function between(StaffRole $from, StaffRole $to): self
    {
        if ($from->grantsAccess() === $to->grantsAccess()) {
            return self::Unchanged;
        }

        return $to->grantsAccess() ? self::Granted : self::Revoked;
    }
}
