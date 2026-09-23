<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\InvalidGuestAddress;
use App\Domains\Appointments\ValueObjects\GuestAddress;

final readonly class GuestAddressInput
{
    public const MAXIMUM_STREET_LENGTH = 160;

    public const MAXIMUM_CITY_LENGTH = 120;

    public const MAXIMUM_STATE_NAME_LENGTH = 120;

    public const MINIMUM_POSTAL_CODE_LENGTH = 4;

    public const MAXIMUM_POSTAL_CODE_LENGTH = 10;

    public const COUNTRY_CODE_LENGTH = 2;

    public function __construct(
        public string $street,
        public ?string $city,
        public ?string $stateName,
        public ?string $postalCode,
        public string $countryCode,
    ) {}

    public static function fromPayload(mixed $payload): ?self
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        return new self(
            street: self::textOrEmpty($payload['street'] ?? null),
            city: self::textOrNull($payload['city'] ?? null),
            stateName: self::textOrNull($payload['state'] ?? null),
            postalCode: self::textOrNull($payload['postal_code'] ?? null),
            countryCode: self::textOrEmpty($payload['country_code'] ?? null),
        );
    }

    /**
     * @throws InvalidGuestAddress
     */
    public function validate(): void
    {
        $this->validateStreet();
        $this->validateCity();
        $this->validateStateName();
        $this->validatePostalCode();
        $this->validateCountryCode();
    }

    public function toAddress(): GuestAddress
    {
        return new GuestAddress(
            street: trim($this->street),
            city: self::trimmedOrNull($this->city),
            stateName: self::trimmedOrNull($this->stateName),
            postalCode: self::trimmedOrNull($this->postalCode),
            countryCode: mb_strtoupper(trim($this->countryCode)),
        );
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private static function trimmedOrNull(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function validateStreet(): void
    {
        $street = trim($this->street);

        if ($street === '') {
            throw InvalidGuestAddress::withoutStreet();
        }

        if (mb_strlen($street) > self::MAXIMUM_STREET_LENGTH) {
            throw InvalidGuestAddress::streetTooLong(self::MAXIMUM_STREET_LENGTH);
        }
    }

    private function validateCity(): void
    {
        if ($this->city !== null && mb_strlen(trim($this->city)) > self::MAXIMUM_CITY_LENGTH) {
            throw InvalidGuestAddress::cityTooLong(self::MAXIMUM_CITY_LENGTH);
        }
    }

    private function validateStateName(): void
    {
        if ($this->stateName !== null && mb_strlen(trim($this->stateName)) > self::MAXIMUM_STATE_NAME_LENGTH) {
            throw InvalidGuestAddress::stateNameTooLong(self::MAXIMUM_STATE_NAME_LENGTH);
        }
    }

    private function validatePostalCode(): void
    {
        if ($this->postalCode === null) {
            return;
        }

        $length = mb_strlen(trim($this->postalCode));

        if ($length < self::MINIMUM_POSTAL_CODE_LENGTH || $length > self::MAXIMUM_POSTAL_CODE_LENGTH) {
            throw InvalidGuestAddress::postalCodeOutOfBounds(
                self::MINIMUM_POSTAL_CODE_LENGTH,
                self::MAXIMUM_POSTAL_CODE_LENGTH,
            );
        }
    }

    private function validateCountryCode(): void
    {
        if (mb_strlen(trim($this->countryCode)) !== self::COUNTRY_CODE_LENGTH) {
            throw InvalidGuestAddress::malformedCountryCode($this->countryCode);
        }
    }
}
