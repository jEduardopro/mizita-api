<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Entities;

use App\Domains\Addresses\Exceptions\InvalidAddressCity;
use App\Domains\Addresses\Exceptions\InvalidAddressStreet;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Addresses\ValueObjects\Coordinates;
use App\Domains\Addresses\ValueObjects\PostalCode;
use App\Shared\ValueObjects\CountryCode;
use DateTimeImmutable;

final class Address
{
    public const MAXIMUM_STREET_LENGTH = 160;

    public const MAXIMUM_CITY_LENGTH = 120;

    private function __construct(
        public readonly string $id,
        public readonly AddressOwnerType $ownerType,
        public readonly string $ownerId,
        private string $street,
        private string $city,
        private ?string $stateId,
        private PostalCode $postalCode,
        private CountryCode $country,
        private ?Coordinates $coordinates,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    /**
     * @throws InvalidAddressStreet
     * @throws InvalidAddressCity
     */
    public static function create(
        string $id,
        AddressOwnerType $ownerType,
        string $ownerId,
        string $street,
        string $city,
        ?string $stateId,
        PostalCode $postalCode,
        CountryCode $country,
        ?Coordinates $coordinates,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            ownerType: $ownerType,
            ownerId: $ownerId,
            street: self::acceptableStreet($street),
            city: self::acceptableCity($city),
            stateId: $stateId,
            postalCode: $postalCode,
            country: $country,
            coordinates: $coordinates,
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        AddressOwnerType $ownerType,
        string $ownerId,
        string $street,
        string $city,
        ?string $stateId,
        PostalCode $postalCode,
        CountryCode $country,
        ?Coordinates $coordinates,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            ownerType: $ownerType,
            ownerId: $ownerId,
            street: $street,
            city: $city,
            stateId: $stateId,
            postalCode: $postalCode,
            country: $country,
            coordinates: $coordinates,
            createdAt: $createdAt,
        );
    }

    /**
     * @throws InvalidAddressStreet
     * @throws InvalidAddressCity
     */
    public function relocateTo(
        string $street,
        string $city,
        ?string $stateId,
        PostalCode $postalCode,
        CountryCode $country,
    ): void {
        $acceptableStreet = self::acceptableStreet($street);
        $acceptableCity = self::acceptableCity($city);

        $this->street = $acceptableStreet;
        $this->city = $acceptableCity;
        $this->stateId = $stateId;
        $this->postalCode = $postalCode;
        $this->country = $country;
    }

    public function pinAt(Coordinates $coordinates): void
    {
        $this->coordinates = $coordinates;
    }

    public function unpin(): void
    {
        $this->coordinates = null;
    }

    public function street(): string
    {
        return $this->street;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function stateId(): ?string
    {
        return $this->stateId;
    }

    public function postalCode(): PostalCode
    {
        return $this->postalCode;
    }

    public function country(): CountryCode
    {
        return $this->country;
    }

    public function coordinates(): ?Coordinates
    {
        return $this->coordinates;
    }

    /**
     * @throws InvalidAddressStreet
     */
    private static function acceptableStreet(string $street): string
    {
        $street = trim($street);

        if ($street === '') {
            throw InvalidAddressStreet::empty();
        }

        if (mb_strlen($street) > self::MAXIMUM_STREET_LENGTH) {
            throw InvalidAddressStreet::tooLong();
        }

        return $street;
    }

    /**
     * @throws InvalidAddressCity
     */
    private static function acceptableCity(string $city): string
    {
        $city = trim($city);

        if ($city === '') {
            throw InvalidAddressCity::empty();
        }

        if (mb_strlen($city) > self::MAXIMUM_CITY_LENGTH) {
            throw InvalidAddressCity::tooLong();
        }

        return $city;
    }
}
