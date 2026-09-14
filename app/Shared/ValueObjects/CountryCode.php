<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

/** The countries the platform operates in, as ISO 3166-1 alpha-2 codes. */
enum CountryCode: string
{
    case Mx = 'MX';

    case Us = 'US';

    /** The only place in the codebase where dial codes are written down. */
    public function dialCode(): string
    {
        return match ($this) {
            self::Mx => '+52',
            self::Us => '+1',
        };
    }
}
