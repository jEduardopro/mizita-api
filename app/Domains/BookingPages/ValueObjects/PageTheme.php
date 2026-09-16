<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\ValueObjects;

use App\Domains\BookingPages\Exceptions\InvalidBookingPageTheme;

enum PageTheme: string
{
    case System = 'system';

    case Light = 'light';

    case Dark = 'dark';

    /**
     * @throws InvalidBookingPageTheme
     */
    public static function fromValue(string $value): self
    {
        $theme = self::tryFrom(mb_strtolower(trim($value)));

        if ($theme === null) {
            throw InvalidBookingPageTheme::withValue($value);
        }

        return $theme;
    }
}
