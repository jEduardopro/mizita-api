<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\ValueObjects;

use App\Domains\BookingPages\Exceptions\InvalidBookingPageButtonShape;

enum ButtonShape: string
{
    case Pill = 'pill';

    case Rounded = 'rounded';

    case Rectangle = 'rectangle';

    /**
     * @throws InvalidBookingPageButtonShape
     */
    public static function fromValue(string $value): self
    {
        $shape = self::tryFrom(mb_strtolower(trim($value)));

        if ($shape === null) {
            throw InvalidBookingPageButtonShape::withValue($value);
        }

        return $shape;
    }
}
