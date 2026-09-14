<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;

final readonly class PhoneNumberInput
{
    private const COUNTRY_CODE_LENGTH = 2;

    private const MAXIMUM_NATIONAL_NUMBER_LENGTH = 24;

    public function __construct(
        public string $countryCode,
        public string $nationalNumber,
    ) {}

    /**
     * @throws UnsupportedPhoneNumber
     */
    public function validate(): void
    {
        $this->validateCountryCode();
        $this->validateNationalNumber();
    }

    private function validateCountryCode(): void
    {
        if (mb_strlen($this->countryCode) !== self::COUNTRY_CODE_LENGTH) {
            throw UnsupportedPhoneNumber::inCountry($this->countryCode);
        }
    }

    private function validateNationalNumber(): void
    {
        if (trim($this->nationalNumber) === '') {
            throw UnsupportedPhoneNumber::malformed($this->countryCode);
        }

        if (mb_strlen($this->nationalNumber) > self::MAXIMUM_NATIONAL_NUMBER_LENGTH) {
            throw UnsupportedPhoneNumber::malformed($this->countryCode);
        }
    }
}
