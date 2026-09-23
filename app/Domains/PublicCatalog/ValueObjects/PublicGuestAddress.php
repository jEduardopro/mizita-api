<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

use App\Domains\PublicCatalog\Exceptions\InvalidPublicGuestAddress;

final readonly class PublicGuestAddress
{
    public const MAXIMUM_STREET_LENGTH = 160;

    public const MAXIMUM_CITY_LENGTH = 120;

    public const MAXIMUM_STATE_LENGTH = 120;

    public const MINIMUM_POSTAL_CODE_LENGTH = 4;

    public const MAXIMUM_POSTAL_CODE_LENGTH = 10;

    public function __construct(
        public ?string $street,
        public ?string $city,
        public ?string $state,
        public ?string $postalCode,
    ) {}

    /**
     * @throws InvalidPublicGuestAddress
     */
    public function validate(): void
    {
        $this->validateStreet();
        $this->validateCity();
        $this->validateState();
        $this->validatePostalCode();
    }

    public function isComplete(): bool
    {
        return $this->street !== null
            && $this->city !== null
            && $this->state !== null
            && $this->postalCode !== null;
    }

    public function isBlank(): bool
    {
        return $this->street === null
            && $this->city === null
            && $this->state === null
            && $this->postalCode === null;
    }

    private static function lengthOf(?string $value): int
    {
        return $value === null ? 0 : mb_strlen(trim($value));
    }

    private function validateStreet(): void
    {
        if (self::lengthOf($this->street) > self::MAXIMUM_STREET_LENGTH) {
            throw InvalidPublicGuestAddress::streetTooLong(self::MAXIMUM_STREET_LENGTH);
        }
    }

    private function validateCity(): void
    {
        if (self::lengthOf($this->city) > self::MAXIMUM_CITY_LENGTH) {
            throw InvalidPublicGuestAddress::cityTooLong(self::MAXIMUM_CITY_LENGTH);
        }
    }

    private function validateState(): void
    {
        if (self::lengthOf($this->state) > self::MAXIMUM_STATE_LENGTH) {
            throw InvalidPublicGuestAddress::stateTooLong(self::MAXIMUM_STATE_LENGTH);
        }
    }

    private function validatePostalCode(): void
    {
        if ($this->postalCode === null) {
            return;
        }

        $length = self::lengthOf($this->postalCode);

        if ($length < self::MINIMUM_POSTAL_CODE_LENGTH || $length > self::MAXIMUM_POSTAL_CODE_LENGTH) {
            throw InvalidPublicGuestAddress::postalCodeOutOfBounds(
                self::MINIMUM_POSTAL_CODE_LENGTH,
                self::MAXIMUM_POSTAL_CODE_LENGTH,
            );
        }
    }
}
