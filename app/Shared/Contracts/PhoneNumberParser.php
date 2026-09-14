<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;

/**
 * In the shared kernel rather than in Phones because the value object it
 * produces already is: Businesses names a PhoneNumber in its own PhoneBook port,
 * and a domain may not import another domain.
 *
 * Null rather than a typed exception, because every way parsing fails reduces to
 * "that is not a real number in the country you picked".
 */
interface PhoneNumberParser
{
    /**
     * @param  string  $nationalNumber  as typed, separators and all
     * @return PhoneNumber|null null when the number is unparsable, invalid, or
     *                          belongs to a country other than the one declared
     */
    public function parse(CountryCode $country, string $nationalNumber): ?PhoneNumber;
}
