<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Gateways;

use App\Domains\Addresses\Application\Dtos\ReplaceAddressInput;
use App\Domains\Addresses\Application\UseCases\ReplaceAddress;
use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\Exceptions\UnsupportedCountry;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Addresses\ValueObjects\PostalCode;
use App\Domains\Customers\Contracts\CustomerAddressBook;
use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;
use App\Shared\ValueObjects\CountryCode;

final class AddressesCustomerAddressBook implements CustomerAddressBook
{
    public function __construct(
        private readonly AddressRepository $addresses,
        private readonly ReplaceAddress $replaceAddress,
    ) {}

    public function forCustomer(string $customerId): ?CustomerAddressSnapshot
    {
        $address = $this->addresses->findForOwner(AddressOwnerType::Customer, $customerId);

        if ($address === null) {
            return null;
        }

        return new CustomerAddressSnapshot(
            street: $address->street(),
            city: $address->city(),
            stateId: $address->stateId(),
            postalCode: $address->postalCode()?->value,
            countryCode: $address->country()->value,
            stateName: $address->stateName(),
        );
    }

    public function replaceForCustomer(string $customerId, CustomerAddressSnapshot $address): void
    {
        $this->replaceAddress->handle(new ReplaceAddressInput(
            ownerType: AddressOwnerType::Customer,
            ownerId: $customerId,
            street: $address->street,
            city: $address->city,
            stateId: $address->stateId,
            postalCode: PostalCode::fromNullable($address->postalCode),
            country: self::country($address->countryCode),
            coordinates: null,
            stateName: $address->stateName,
        ))->value();
    }

    public function removeForCustomer(string $customerId): void
    {
        $this->addresses->deleteForOwner(AddressOwnerType::Customer, $customerId);
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
}
