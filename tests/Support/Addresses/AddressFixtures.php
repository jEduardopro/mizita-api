<?php

declare(strict_types=1);

namespace Tests\Support\Addresses;

use App\Domains\Addresses\Entities\Address;
use App\Domains\Addresses\Entities\State;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Addresses\ValueObjects\Coordinates;
use App\Domains\Addresses\ValueObjects\PostalCode;
use App\Shared\ValueObjects\CountryCode;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class AddressFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const ADDRESS_ID = '01930000-0000-7000-8000-0000000000a1';

    public const GENERATED_ADDRESS_ID = '01930000-0000-7000-8000-0000000000a9';

    public const OWNER_KEY = 42;

    public const STAFF_OWNER_ID = '01930000-0000-7000-8000-0000000000d1';

    public const STATE_ID = '01930000-0000-7000-8000-0000000000f1';

    public const SECOND_STATE_ID = '01930000-0000-7000-8000-0000000000f2';

    public const STATE_KEY = 9;

    public const STREET = 'Avenida Insurgentes Sur 1602';

    public const CITY = 'Ciudad de México';

    public const POSTAL_CODE = '03940';

    public const LATITUDE = 19.3627888;

    public const LONGITUDE = -99.1768069;

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function address(
        string $id = self::ADDRESS_ID,
        AddressOwnerType $ownerType = AddressOwnerType::Business,
        string $ownerId = FakeBusinessContext::BUSINESS_ID,
        string $street = self::STREET,
        ?string $city = self::CITY,
        ?string $stateId = self::STATE_ID,
        ?string $postalCode = self::POSTAL_CODE,
        CountryCode $country = CountryCode::Mx,
        ?Coordinates $coordinates = null,
        ?DateTimeImmutable $createdAt = null,
        ?string $stateName = null,
    ): Address {
        return Address::restore(
            id: $id,
            ownerType: $ownerType,
            ownerId: $ownerId,
            street: $street,
            city: $city,
            stateId: $stateId,
            postalCode: $postalCode === null ? null : PostalCode::restore($postalCode),
            country: $country,
            coordinates: $coordinates,
            createdAt: $createdAt ?? self::now(),
            stateName: $stateName,
        );
    }

    public static function streetOnly(
        string $id = self::ADDRESS_ID,
        AddressOwnerType $ownerType = AddressOwnerType::Business,
        string $ownerId = FakeBusinessContext::BUSINESS_ID,
        string $street = self::STREET,
        CountryCode $country = CountryCode::Mx,
        ?DateTimeImmutable $createdAt = null,
    ): Address {
        return self::address(
            id: $id,
            ownerType: $ownerType,
            ownerId: $ownerId,
            street: $street,
            city: null,
            stateId: null,
            postalCode: null,
            country: $country,
            coordinates: null,
            createdAt: $createdAt,
        );
    }

    public static function coordinates(
        float $latitude = self::LATITUDE,
        float $longitude = self::LONGITUDE,
    ): Coordinates {
        return Coordinates::restore($latitude, $longitude);
    }

    public static function state(
        string $id = self::STATE_ID,
        CountryCode $country = CountryCode::Mx,
        string $code = 'CMX',
        string $name = 'Ciudad de México',
        int $position = 9,
        bool $active = true,
    ): State {
        return State::restore(
            id: $id,
            country: $country,
            code: $code,
            name: $name,
            position: $position,
            active: $active,
        );
    }
}
