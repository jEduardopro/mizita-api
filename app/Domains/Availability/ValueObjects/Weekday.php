<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

use App\Domains\Availability\Exceptions\InvalidWeekday;

enum Weekday: int
{
    case Monday = 1;

    case Tuesday = 2;

    case Wednesday = 3;

    case Thursday = 4;

    case Friday = 5;

    case Saturday = 6;

    case Sunday = 7;

    /**
     * @throws InvalidWeekday
     */
    public static function fromNumber(int $number): self
    {
        $weekday = self::tryFrom($number);

        if ($weekday === null) {
            throw InvalidWeekday::withNumber($number);
        }

        return $weekday;
    }
}
