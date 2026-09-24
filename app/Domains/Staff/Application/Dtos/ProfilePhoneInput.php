<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Dtos;

use App\Domains\Staff\Exceptions\InvalidProfilePhone;

final readonly class ProfilePhoneInput
{
    public const MAXIMUM_NATIONAL_NUMBER_LENGTH = 24;

    public const COUNTRY_CODE_LENGTH = 2;

    public function __construct(
        public string $countryCode,
        public string $nationalNumber,
    ) {}

    public static function fromPayload(mixed $payload): ?self
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return new self(
            countryCode: self::textOrEmpty($payload['country_code'] ?? null),
            nationalNumber: self::textOrEmpty($payload['national_number'] ?? null),
        );
    }

    /**
     * @throws InvalidProfilePhone
     */
    public function validate(): void
    {
        $this->validateCountryCode();
        $this->validateNationalNumber();
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function validateCountryCode(): void
    {
        if (mb_strlen(trim($this->countryCode)) !== self::COUNTRY_CODE_LENGTH) {
            throw InvalidProfilePhone::inCountry($this->countryCode);
        }
    }

    private function validateNationalNumber(): void
    {
        $number = trim($this->nationalNumber);

        if ($number === '') {
            throw InvalidProfilePhone::malformed($this->countryCode);
        }

        if (mb_strlen($number) > self::MAXIMUM_NATIONAL_NUMBER_LENGTH) {
            throw InvalidProfilePhone::malformed($this->countryCode);
        }
    }
}
