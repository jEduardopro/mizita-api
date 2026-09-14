<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

enum CountryCode: string
{
    case Mx = 'MX';

    case Us = 'US';

    public function dialCode(): string
    {
        return match ($this) {
            self::Mx => '+52',
            self::Us => '+1',
        };
    }
}
