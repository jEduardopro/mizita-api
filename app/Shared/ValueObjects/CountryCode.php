<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

/**
 * The countries the platform operates in, as ISO 3166-1 alpha-2 codes.
 *
 * A backed enum rather than a free string, so an unsupported country cannot
 * reach the database and adding one is a single case here.
 */
enum CountryCode: string
{
    case Mx = 'MX';

    case Us = 'US';

    /**
     * The E.164 country calling code, plus sign included.
     *
     * This method is the only place in the codebase where these dial codes are
     * written down. Anything that needs one asks a CountryCode for it, so a new
     * country never turns into a second table of prefixes somewhere else.
     */
    public function dialCode(): string
    {
        return match ($this) {
            self::Mx => '+52',
            self::Us => '+1',
        };
    }
}
