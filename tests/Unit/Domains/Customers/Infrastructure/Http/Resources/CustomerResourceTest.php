<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CustomerAddressData;
use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Infrastructure\Http\Resources\CustomerResource;
use Tests\Support\Customers\CustomerFixtures;
use Tests\TestCase;

uses(TestCase::class);

function customerResourceData(?CustomerAddressData $address): CustomerData
{
    return new CustomerData(
        id: CustomerFixtures::CUSTOMER_ID,
        name: CustomerFixtures::NAME,
        email: null,
        phone: null,
        birthDate: null,
        notes: null,
        address: $address,
        photoUrl: null,
        createdAt: CustomerFixtures::now(),
    );
}

/**
 * @return array<string, mixed>
 */
function serializedCustomer(CustomerData $customer): array
{
    return (array) CustomerResource::make($customer)->response()->getData(true)['data'];
}

it('paints the address block with the typed state name next to the catalogue state', function () {
    $body = serializedCustomer(customerResourceData(new CustomerAddressData(
        street: CustomerFixtures::STREET,
        city: CustomerFixtures::CITY,
        stateId: null,
        stateName: CustomerFixtures::STATE_NAME,
        postalCode: CustomerFixtures::POSTAL_CODE,
        countryCode: CustomerFixtures::COUNTRY_CODE,
    )));

    expect($body['address'])->toBe([
        'street' => CustomerFixtures::STREET,
        'city' => CustomerFixtures::CITY,
        'state_id' => null,
        'state_name' => CustomerFixtures::STATE_NAME,
        'postal_code' => CustomerFixtures::POSTAL_CODE,
        'country_code' => CustomerFixtures::COUNTRY_CODE,
    ]);
});

it('paints the catalogue state by its uuid and a null state name when none was typed', function () {
    $body = serializedCustomer(customerResourceData(new CustomerAddressData(
        street: CustomerFixtures::STREET,
        city: null,
        stateId: CustomerFixtures::STATE_ID,
        stateName: null,
        postalCode: null,
        countryCode: CustomerFixtures::COUNTRY_CODE,
    )));

    expect($body['address']['state_id'])->toBe(CustomerFixtures::STATE_ID)
        ->and($body['address'])->toHaveKey('state_name')
        ->and($body['address']['state_name'])->toBeNull();
});

it('paints a customer with no address as a null block', function () {
    expect(serializedCustomer(customerResourceData(null))['address'])->toBeNull();
});
