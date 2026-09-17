<?php

declare(strict_types=1);

namespace App\Domains\Customers\ValueObjects;

use App\Domains\Customers\Exceptions\InvalidCustomerBirthDate;
use DateTimeImmutable;

final readonly class BirthDate
{
    public const FORMAT = 'Y-m-d';

    private const AT_MIDNIGHT = '!'.self::FORMAT;

    /**
     * @throws InvalidCustomerBirthDate
     */
    public static function fromNullable(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat(self::AT_MIDNIGHT, $value);

        if ($date === false || $date->format(self::FORMAT) !== $value) {
            throw InvalidCustomerBirthDate::malformed($value);
        }

        return $date;
    }
}
