<?php

declare(strict_types=1);

use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\Entities\Address;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AddressesPublishedLocation;
use App\Domains\PublicCatalog\ValueObjects\PublicLocation;
use App\Shared\ValueObjects\CountryCode;
use Tests\Support\Addresses\AddressFixtures;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->addresses = Mockery::mock(AddressRepository::class);

    $this->gateway = new AddressesPublishedLocation($this->addresses);

    $this->read = fn (): ?PublicLocation => $this->gateway->forBusiness(PublicCatalogFixtures::BUSINESS_ID);
});

describe('the address a visitor walks to', function () {
    it('publishes the street, the city and the postal code the business filed', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(
            AddressFixtures::address(ownerId: PublicCatalogFixtures::BUSINESS_ID, stateId: null),
        );

        $location = ($this->read)();

        expect($location)->toBeInstanceOf(PublicLocation::class)
            ->and($location->street)->toBe(AddressFixtures::STREET)
            ->and($location->city)->toBe(AddressFixtures::CITY)
            ->and($location->postalCode)->toBe(AddressFixtures::POSTAL_CODE)
            ->and($location->countryCode)->toBe('MX');
    });

    it('asks for the address of the business, under its uuid', function () {
        $ownerId = null;

        $this->addresses->shouldReceive('findForOwner')->once()
            ->with(AddressOwnerType::Business, Mockery::capture($ownerId))
            ->andReturnNull();

        ($this->read)();

        expect($ownerId)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and(is_numeric($ownerId))->toBeFalse();
    });

    it('answers with nothing for a business that filed no address', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        expect(($this->read)())->toBeNull();
    });

    it('publishes the state by its name, never by the row that stores it', function () {
        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicLocation::class))->getProperties(),
        );

        expect($fields)->toBe(['street', 'city', 'state', 'postalCode', 'countryCode', 'latitude', 'longitude'])
            ->and($fields)->not->toContain('stateId');
    });

    it('publishes null city and postal code for a business that filed a street alone', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(Address::restore(
            id: AddressFixtures::ADDRESS_ID,
            ownerType: AddressOwnerType::Business,
            ownerId: PublicCatalogFixtures::BUSINESS_ID,
            street: AddressFixtures::STREET,
            city: null,
            stateId: null,
            postalCode: null,
            country: CountryCode::Mx,
            coordinates: null,
            createdAt: AddressFixtures::now(),
        ));

        $location = ($this->read)();

        expect($location)->toBeInstanceOf(PublicLocation::class)
            ->and($location->street)->toBe(AddressFixtures::STREET)
            ->and($location->city)->toBeNull()
            ->and($location->state)->toBeNull()
            ->and($location->postalCode)->toBeNull()
            ->and($location->countryCode)->toBe('MX');
    });

    it('sends no state for an address filed without one', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(
            AddressFixtures::address(ownerId: PublicCatalogFixtures::BUSINESS_ID, stateId: null),
        );

        expect(($this->read)()->state)->toBeNull();
    });
});

describe('the pin on the map', function () {
    it('publishes the coordinates as strings, so no decimal is lost to a float', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(AddressFixtures::address(
            ownerId: PublicCatalogFixtures::BUSINESS_ID,
            stateId: null,
            coordinates: AddressFixtures::coordinates(),
        ));

        $location = ($this->read)();

        expect($location->latitude)->toBe('19.3627888')
            ->toBeString()
            ->and($location->longitude)->toBe('-99.1768069')
            ->toBeString();
    });

    it('pads every coordinate to the scale the column stores', function (float $latitude, string $expected) {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(AddressFixtures::address(
            ownerId: PublicCatalogFixtures::BUSINESS_ID,
            stateId: null,
            coordinates: AddressFixtures::coordinates(latitude: $latitude, longitude: 0.0),
        ));

        expect(($this->read)()->latitude)->toBe($expected);
    })->with([
        'a whole degree' => [19.0, '19.0000000'],
        'the equator' => [0.0, '0.0000000'],
        'south of it' => [-33.5, '-33.5000000'],
        'the last decimal that matters' => [19.3627888, '19.3627888'],
    ]);

    it('sends no coordinates for an address nobody pinned', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(
            AddressFixtures::address(ownerId: PublicCatalogFixtures::BUSINESS_ID, stateId: null),
        );

        $location = ($this->read)();

        expect($location->latitude)->toBeNull()
            ->and($location->longitude)->toBeNull();
    });
});
