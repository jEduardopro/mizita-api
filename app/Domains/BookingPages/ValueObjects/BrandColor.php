<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\ValueObjects;

use App\Domains\BookingPages\Exceptions\InvalidBookingPageAccentColor;

enum BrandColor: string
{
    case Ink = 'ink';

    case Red = 'red';

    case Orange = 'orange';

    case Amber = 'amber';

    case Purple = 'purple';

    case Blue = 'blue';

    case Sand = 'sand';

    case Slate = 'slate';

    case Teal = 'teal';

    case Green = 'green';

    /**
     * @throws InvalidBookingPageAccentColor
     */
    public static function fromValue(string $value): self
    {
        $color = self::tryFrom(mb_strtolower(trim($value)));

        if ($color === null) {
            throw InvalidBookingPageAccentColor::withValue($value);
        }

        return $color;
    }
}
