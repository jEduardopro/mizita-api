<?php

declare(strict_types=1);

use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AddressesPublishedCity;
use Tests\Support\Addresses\AddressFixtures;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->addresses = Mockery::mock(AddressRepository::class);

    $this->gateway = new AddressesPublishedCity($this->addresses);

    $this->read = fn (): ?string => $this->gateway->forBusiness(PublicCatalogFixtures::BUSINESS_ID);
});

it('publishes the city the business filed, accents intact', function () {
    $this->addresses->shouldReceive('findForOwner')->once()->andReturn(
        AddressFixtures::address(ownerId: PublicCatalogFixtures::BUSINESS_ID, city: 'Ciudad de México'),
    );

    expect(($this->read)())->toBe('Ciudad de México');
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

it('answers with no city for a business that filed no address', function () {
    $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

    expect(($this->read)())->toBeNull();
});

it('answers with no city for an address filed without one', function () {
    $this->addresses->shouldReceive('findForOwner')->once()->andReturn(
        AddressFixtures::address(ownerId: PublicCatalogFixtures::BUSINESS_ID, city: null),
    );

    expect(($this->read)())->toBeNull();
});
