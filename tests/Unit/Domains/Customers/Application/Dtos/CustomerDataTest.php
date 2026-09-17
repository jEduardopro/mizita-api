<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CustomerAddressData;
use App\Domains\Customers\Application\Dtos\CustomerData;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use Tests\Support\FakeBusinessContext;
use Tests\Support\PhoneNumbers;

function customerToPresent(
    ?CustomerEmail $email = null,
    ?DateTimeImmutable $birthDate = null,
    ?string $notes = null,
): Customer {
    return Customer::create(
        id: '01930000-0000-7000-8000-0000000000c1',
        businessId: FakeBusinessContext::BUSINESS_ID,
        name: 'Ada Lovelace',
        email: $email,
        birthDate: $birthDate,
        notes: $notes,
        now: new DateTimeImmutable('2026-01-01 12:00:00'),
    );
}

function customerAddressSnapshot(): CustomerAddressSnapshot
{
    return new CustomerAddressSnapshot(
        street: 'Avenida Insurgentes Sur 1602',
        city: 'Ciudad de México',
        stateId: '01930000-0000-7000-8000-0000000000e1',
        postalCode: '03940',
        countryCode: 'MX',
    );
}

it('reads every fact a customer answers with off the entity and its neighbours', function () {
    $data = CustomerData::fromEntity(
        customerToPresent(
            email: CustomerEmail::fromString('ada@example.com'),
            birthDate: new DateTimeImmutable('1990-05-17 00:00:00'),
            notes: 'Prefers the afternoon.',
        ),
        PhoneNumbers::mexican(),
        customerAddressSnapshot(),
        'https://cdn.mizita.test/customers/ada.jpg',
    );

    expect($data->id)->toBe('01930000-0000-7000-8000-0000000000c1')
        ->and($data->name)->toBe('Ada Lovelace')
        ->and($data->email)->toBe('ada@example.com')
        ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
        ->and($data->birthDate)->toEqual(new DateTimeImmutable('1990-05-17 00:00:00'))
        ->and($data->notes)->toBe('Prefers the afternoon.')
        ->and($data->address)->toBeInstanceOf(CustomerAddressData::class)
        ->and($data->photoUrl)->toBe('https://cdn.mizita.test/customers/ada.jpg')
        ->and($data->createdAt)->toEqual(new DateTimeImmutable('2026-01-01 12:00:00'));
});

it('names the customer by the uuid, which is the only identity that leaves the application', function () {
    $data = CustomerData::fromEntity(customerToPresent(), null, null, null);

    expect($data->id)->toBe('01930000-0000-7000-8000-0000000000c1')
        ->and($data->id)->not->toBe('1');
});

it('carries no business, because a caller only ever reads its own', function () {
    expect(CustomerData::fromEntity(customerToPresent(), null, null, null))
        ->not->toHaveProperty('businessId');
});

it('flattens the email to the string the wire carries rather than the value object', function () {
    $data = CustomerData::fromEntity(
        customerToPresent(email: CustomerEmail::fromString('Ada@EXAMPLE.COM')),
        null,
        null,
        null,
    );

    expect($data->email)->toBe('Ada@example.com')->toBeString();
});

it('answers with nothing for every fact the customer never gave', function () {
    $data = CustomerData::fromEntity(customerToPresent(), null, null, null);

    expect($data->email)->toBeNull()
        ->and($data->phone)->toBeNull()
        ->and($data->birthDate)->toBeNull()
        ->and($data->notes)->toBeNull()
        ->and($data->address)->toBeNull()
        ->and($data->photoUrl)->toBeNull();
});

it('carries the photo url exactly as the port handed it over', function () {
    $data = CustomerData::fromEntity(customerToPresent(), null, null, 'https://cdn.mizita.test/customers/ada.jpg');

    expect($data->photoUrl)->toBe('https://cdn.mizita.test/customers/ada.jpg')->toBeString();
});

it('reads a customer with a phone but no address, and one with an address but no phone', function () {
    $withPhone = CustomerData::fromEntity(customerToPresent(), PhoneNumbers::mexican(), null, null);
    $withAddress = CustomerData::fromEntity(customerToPresent(), null, customerAddressSnapshot(), null);

    expect($withPhone->phone?->e164())->toBe(PhoneNumbers::MX_E164)
        ->and($withPhone->address)->toBeNull()
        ->and($withAddress->phone)->toBeNull()
        ->and($withAddress->address?->street)->toBe('Avenida Insurgentes Sur 1602');
});

it('turns the address snapshot into the shape the resource paints', function () {
    $address = CustomerData::fromEntity(customerToPresent(), null, customerAddressSnapshot(), null)->address;

    expect($address?->street)->toBe('Avenida Insurgentes Sur 1602')
        ->and($address?->city)->toBe('Ciudad de México')
        ->and($address?->stateId)->toBe('01930000-0000-7000-8000-0000000000e1')
        ->and($address?->postalCode)->toBe('03940')
        ->and($address?->countryCode)->toBe('MX');
});

it('names the state by the uuid the snapshot carried, never by a row number', function () {
    $address = CustomerAddressData::fromSnapshot(customerAddressSnapshot());

    expect($address->stateId)->toBe('01930000-0000-7000-8000-0000000000e1')
        ->and($address->stateId)->not->toBe('7');
});

it('carries an address that names a street and nothing around it', function () {
    $address = CustomerAddressData::fromSnapshot(new CustomerAddressSnapshot(
        street: 'Avenida Insurgentes Sur 1602',
        city: null,
        stateId: null,
        postalCode: null,
        countryCode: 'MX',
    ));

    expect($address->street)->toBe('Avenida Insurgentes Sur 1602')
        ->and($address->city)->toBeNull()
        ->and($address->stateId)->toBeNull()
        ->and($address->postalCode)->toBeNull()
        ->and($address->countryCode)->toBe('MX');
});
