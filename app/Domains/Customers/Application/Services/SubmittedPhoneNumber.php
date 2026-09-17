<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Services;

use App\Domains\Customers\Application\Dtos\CustomerPhoneInput;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;

final class SubmittedPhoneNumber
{
    public function __construct(
        private readonly PhoneNumberParser $parser,
    ) {}

    /**
     * @throws InvalidCustomerPhone
     */
    public function parse(?CustomerPhoneInput $submitted): ?PhoneNumber
    {
        if ($submitted === null) {
            return null;
        }

        $country = CountryCode::tryFrom(mb_strtoupper(trim($submitted->countryCode)));

        if ($country === null) {
            throw InvalidCustomerPhone::inCountry($submitted->countryCode);
        }

        $number = $this->parser->parse($country, $submitted->nationalNumber);

        if ($number === null) {
            throw InvalidCustomerPhone::forCountry($country);
        }

        return $number;
    }
}
