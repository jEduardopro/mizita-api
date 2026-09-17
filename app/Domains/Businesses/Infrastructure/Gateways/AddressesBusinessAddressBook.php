<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Addresses\Application\Dtos\ReplaceAddressInput;
use App\Domains\Addresses\Application\UseCases\ReplaceAddress;
use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\Exceptions\InvalidCoordinates;
use App\Domains\Addresses\Exceptions\UnsupportedCountry;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Addresses\ValueObjects\Coordinates;
use App\Domains\Addresses\ValueObjects\PostalCode;
use App\Domains\Businesses\Contracts\BusinessAddressBook;
use App\Domains\Businesses\ValueObjects\BusinessAddressSnapshot;
use App\Shared\ValueObjects\CountryCode;

final class AddressesBusinessAddressBook implements BusinessAddressBook
{
    private const COORDINATE_SCALE = 7;

    private const DECIMAL_SEPARATOR = '.';

    public function __construct(
        private readonly AddressRepository $addresses,
        private readonly ReplaceAddress $replaceAddress,
    ) {}

    public function forBusiness(string $businessId): ?BusinessAddressSnapshot
    {
        $address = $this->addresses->findForOwner(AddressOwnerType::Business, $businessId);

        if ($address === null) {
            return null;
        }

        $coordinates = $address->coordinates();

        return new BusinessAddressSnapshot(
            street: $address->street(),
            city: $address->city(),
            stateId: $address->stateId(),
            postalCode: $address->postalCode()?->value,
            countryCode: $address->country()->value,
            latitude: self::decimal($coordinates?->latitude),
            longitude: self::decimal($coordinates?->longitude),
        );
    }

    public function replaceForBusiness(string $businessId, BusinessAddressSnapshot $address): void
    {
        $this->replaceAddress->handle(new ReplaceAddressInput(
            ownerType: AddressOwnerType::Business,
            ownerId: $businessId,
            street: $address->street,
            city: $address->city,
            stateId: $address->stateId,
            postalCode: PostalCode::fromNullable($address->postalCode),
            country: self::country($address->countryCode),
            coordinates: self::coordinates($address),
        ))->value();
    }

    /**
     * @throws UnsupportedCountry
     */
    private static function country(string $countryCode): CountryCode
    {
        $country = CountryCode::tryFrom(mb_strtoupper(trim($countryCode)));

        if ($country === null) {
            throw UnsupportedCountry::withCode($countryCode);
        }

        return $country;
    }

    /**
     * @throws InvalidCoordinates
     */
    private static function coordinates(BusinessAddressSnapshot $address): ?Coordinates
    {
        $latitude = $address->latitude;
        $longitude = $address->longitude;

        if ($latitude === null && $longitude === null) {
            return null;
        }

        if ($latitude === null) {
            throw InvalidCoordinates::missingLatitude((string) $longitude);
        }

        if ($longitude === null) {
            throw InvalidCoordinates::missingLongitude($latitude);
        }

        return Coordinates::of((float) $latitude, (float) $longitude);
    }

    private static function decimal(?float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return number_format($value, self::COORDINATE_SCALE, self::DECIMAL_SEPARATOR, '');
    }
}
