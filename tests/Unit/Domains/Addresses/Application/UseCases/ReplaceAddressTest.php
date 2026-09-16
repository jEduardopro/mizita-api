<?php

declare(strict_types=1);

use App\Domains\Addresses\Application\Dtos\AddressData;
use App\Domains\Addresses\Application\Dtos\ReplaceAddressInput;
use App\Domains\Addresses\Application\UseCases\ReplaceAddress;
use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\Entities\Address;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Addresses\ValueObjects\Coordinates;
use App\Domains\Addresses\ValueObjects\PostalCode;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Addresses\AddressFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

function replaceAddressInput(
    string $street = AddressFixtures::STREET,
    string $city = AddressFixtures::CITY,
    ?string $stateId = AddressFixtures::STATE_ID,
    string $postalCode = AddressFixtures::POSTAL_CODE,
    CountryCode $country = CountryCode::Mx,
    ?Coordinates $coordinates = null,
    AddressOwnerType $ownerType = AddressOwnerType::Business,
    string $ownerId = FakeBusinessContext::BUSINESS_ID,
): ReplaceAddressInput {
    return new ReplaceAddressInput(
        ownerType: $ownerType,
        ownerId: $ownerId,
        street: $street,
        city: $city,
        stateId: $stateId,
        postalCode: PostalCode::restore($postalCode),
        country: $country,
        coordinates: $coordinates,
    );
}

beforeEach(function () {
    $this->addresses = Mockery::mock(AddressRepository::class);
    $this->clock = new FakeClock(AddressFixtures::now());
    $this->useCase = new ReplaceAddress(
        $this->addresses,
        new FixedIdGenerator(AddressFixtures::GENERATED_ADDRESS_ID),
        $this->clock,
    );
});

describe('the owner has no address yet', function () {
    beforeEach(function () {
        $this->addresses->shouldReceive('findForOwner')->once()
            ->with(AddressOwnerType::Business, FakeBusinessContext::BUSINESS_ID)
            ->andReturnNull();
    });

    it('writes a new address and hands its data back, field by field', function () {
        $this->addresses->shouldReceive('save')->once()
            ->with(Mockery::on(fn (Address $address): bool => $address->id === AddressFixtures::GENERATED_ADDRESS_ID
                && $address->ownerId === FakeBusinessContext::BUSINESS_ID
                && $address->street() === AddressFixtures::STREET));

        $data = $this->useCase->handle(replaceAddressInput(coordinates: AddressFixtures::coordinates()))->value();

        expect($data)->toBeInstanceOf(AddressData::class)
            ->and($data->id)->toBe(AddressFixtures::GENERATED_ADDRESS_ID)
            ->and($data->street)->toBe(AddressFixtures::STREET)
            ->and($data->city)->toBe(AddressFixtures::CITY)
            ->and($data->stateId)->toBe(AddressFixtures::STATE_ID)
            ->and($data->postalCode)->toBe(AddressFixtures::POSTAL_CODE)
            ->and($data->countryCode)->toBe('MX')
            ->and($data->latitude)->toBe(AddressFixtures::LATITUDE)
            ->and($data->longitude)->toBe(AddressFixtures::LONGITUDE);
    });

    it('stamps the new address with the injected clock', function () {
        $this->addresses->shouldReceive('save')->once()
            ->with(Mockery::on(fn (Address $address): bool => $address->createdAt == AddressFixtures::now()));

        $this->useCase->handle(replaceAddressInput());
    });

    it('leaves the address unpinned when the caller sent no point', function () {
        $this->addresses->shouldReceive('save')->once();

        $data = $this->useCase->handle(replaceAddressInput())->value();

        expect($data->latitude)->toBeNull()
            ->and($data->longitude)->toBeNull();
    });

    it('saves nothing and refuses when the street is one the entity will not take', function () {
        $this->addresses->shouldNotReceive('save');

        $response = $this->useCase->handle(replaceAddressInput(street: '   '));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_address_street')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
    });

    it('saves nothing and refuses when the city is one the entity will not take', function () {
        $this->addresses->shouldNotReceive('save');

        $response = $this->useCase->handle(replaceAddressInput(city: ''));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_address_city');
    });
});

describe('the owner already has an address', function () {
    beforeEach(function () {
        $this->existing = AddressFixtures::address(
            coordinates: AddressFixtures::coordinates(),
            createdAt: new DateTimeImmutable('2025-06-01T08:00:00+00:00'),
        );

        $this->addresses->shouldReceive('findForOwner')->once()->andReturn($this->existing);
    });

    it('moves the address it found instead of minting a second one', function () {
        $this->addresses->shouldReceive('save')->once()
            ->with(Mockery::on(fn (Address $address): bool => $address === $this->existing));

        $data = $this->useCase->handle(replaceAddressInput(street: 'Paseo de la Reforma 222'))->value();

        expect($data->id)->toBe(AddressFixtures::ADDRESS_ID)
            ->and($data->id)->not->toBe(AddressFixtures::GENERATED_ADDRESS_ID)
            ->and($data->street)->toBe('Paseo de la Reforma 222');
    });

    it('keeps the instant the address was first recorded', function () {
        $this->addresses->shouldReceive('save')->once();

        $this->useCase->handle(replaceAddressInput());

        expect($this->existing->createdAt)->toEqual(new DateTimeImmutable('2025-06-01T08:00:00+00:00'));
    });

    it('unpins the address when the caller sent no point this time', function () {
        $this->addresses->shouldReceive('save')->once();

        $data = $this->useCase->handle(replaceAddressInput())->value();

        expect($data->latitude)->toBeNull()
            ->and($this->existing->coordinates())->toBeNull();
    });

    it('repins the address on the point the caller sent', function () {
        $this->addresses->shouldReceive('save')->once();

        $data = $this->useCase->handle(replaceAddressInput(
            coordinates: AddressFixtures::coordinates(latitude: 25.6866142, longitude: -100.3161126),
        ))->value();

        expect($data->latitude)->toBe(25.6866142)
            ->and($data->longitude)->toBe(-100.3161126);
    });

    it('saves nothing and refuses a move the entity will not take', function () {
        $this->addresses->shouldNotReceive('save');

        expect($this->useCase->handle(replaceAddressInput(street: ''))->error()->code)
            ->toBe('invalid_address_street');
    });
});

it('looks the address up by the owner it was handed, kind and uuid alike', function () {
    $this->addresses->shouldReceive('findForOwner')->once()
        ->with(AddressOwnerType::StaffMember, AddressFixtures::STAFF_OWNER_ID)
        ->andReturnNull();
    $this->addresses->shouldReceive('save')->once();

    $this->useCase->handle(replaceAddressInput(
        ownerType: AddressOwnerType::StaffMember,
        ownerId: AddressFixtures::STAFF_OWNER_ID,
    ));
});
