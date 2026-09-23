<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\ValueObjects;

enum ContactFieldRequirement: string
{
    case Hidden = 'hidden';

    case Optional = 'optional';

    case Required = 'required';

    public function isCollected(): bool
    {
        return $this !== self::Hidden;
    }

    public function isRequired(): bool
    {
        return $this === self::Required;
    }
}
