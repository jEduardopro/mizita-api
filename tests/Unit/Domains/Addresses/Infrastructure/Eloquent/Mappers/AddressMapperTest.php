<?php

declare(strict_types=1);

use App\Domains\Addresses\Infrastructure\Eloquent\Mappers\AddressMapper;
use App\Domains\Addresses\Infrastructure\Eloquent\Models\AddressModel;
use App\Domains\Addresses\Infrastructure\Eloquent\Models\StateModel;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Shared\ValueObjects\CountryCode;
use Tests\Support\Addresses\AddressFixtures;
use Tests\Support\FakeBusinessContext;

function stateRow(string $uuid = AddressFixtures::STATE_ID): StateModel
{
    $state = new StateModel;

    $state->setRawAttributes([
        'id' => AddressFixtures::STATE_KEY,
        'uuid' => $uuid,
        'country_code' => 'MX',
        'code' => 'CMX',
        'name' => 'Ciudad de México',
        'position' => 9,
        'active' => true,
    ], true);

    return $state;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function addressRow(array $overrides = [], bool $withState = true): AddressModel
{
    $address = new AddressModel;

    $address->setRawAttributes([
        'id' => 7,
        'uuid' => AddressFixtures::ADDRESS_ID,
        'addressable_type' => 'business',
        'addressable_id' => AddressFixtures::OWNER_KEY,
        'street' => AddressFixtures::STREET,
        'city' => AddressFixtures::CITY,
        'state_id' => AddressFixtures::STATE_KEY,
        'postal_code' => AddressFixtures::POSTAL_CODE,
        'country_code' => 'MX',
        'latitude' => '19.3627888',
        'longitude' => '-99.1768069',
        'created_at' => AddressFixtures::now(),
        ...$overrides,
    ], true);

    $address->setRelation('state', $withState ? stateRow() : null);

    return $address;
}

beforeEach(function () {
    $this->mapper = new AddressMapper;
});

describe('entity to row', function () {
    it('spreads the address across its ten columns', function () {
        $address = AddressFixtures::address(coordinates: AddressFixtures::coordinates());

        expect($this->mapper->toAttributes($address, AddressFixtures::OWNER_KEY, AddressFixtures::STATE_KEY))
            ->toBe([
                'uuid' => AddressFixtures::ADDRESS_ID,
                'addressable_type' => 'business',
                'addressable_id' => AddressFixtures::OWNER_KEY,
                'street' => AddressFixtures::STREET,
                'city' => AddressFixtures::CITY,
                'state_id' => AddressFixtures::STATE_KEY,
                'postal_code' => AddressFixtures::POSTAL_CODE,
                'country_code' => 'MX',
                'latitude' => AddressFixtures::LATITUDE,
                'longitude' => AddressFixtures::LONGITUDE,
            ]);
    });

    it('writes the owner as the int key it was handed, never as the uuid', function () {
        $attributes = $this->mapper->toAttributes(AddressFixtures::address(), AddressFixtures::OWNER_KEY, null);

        expect($attributes['addressable_id'])->toBe(AddressFixtures::OWNER_KEY)
            ->and($attributes['addressable_id'])->toBeInt()
            ->and($attributes)->not->toContain(FakeBusinessContext::BUSINESS_ID);
    });

    it('writes the state as the int key it was handed, never as the uuid', function () {
        $attributes = $this->mapper->toAttributes(
            AddressFixtures::address(),
            AddressFixtures::OWNER_KEY,
            AddressFixtures::STATE_KEY,
        );

        expect($attributes['state_id'])->toBe(AddressFixtures::STATE_KEY)
            ->and($attributes)->not->toContain(AddressFixtures::STATE_ID);
    });

    it('writes the owner kind as the stored alias', function (AddressOwnerType $ownerType, string $alias) {
        $address = AddressFixtures::address(ownerType: $ownerType);

        expect($this->mapper->toAttributes($address, AddressFixtures::OWNER_KEY, null)['addressable_type'])
            ->toBe($alias);
    })->with([
        'business' => [AddressOwnerType::Business, 'business'],
        'staff member' => [AddressOwnerType::StaffMember, 'staff_member'],
    ]);

    it('writes both halves of the point as null when the address is unpinned', function () {
        $attributes = $this->mapper->toAttributes(AddressFixtures::address(), AddressFixtures::OWNER_KEY, null);

        expect($attributes['latitude'])->toBeNull()
            ->and($attributes['longitude'])->toBeNull();
    });

    it('writes no state when the address sits in a place with none on record', function () {
        expect($this->mapper->toAttributes(AddressFixtures::address(stateId: null), AddressFixtures::OWNER_KEY, null)['state_id'])->toBeNull();
    });

    it('never writes the internal primary key', function () {
        expect($this->mapper->toAttributes(AddressFixtures::address(), AddressFixtures::OWNER_KEY, null))
            ->not->toHaveKey('id');
    });
});

describe('row to entity', function () {
    it('takes the identity from the uuid column, not from the primary key', function () {
        expect($this->mapper->toEntity(addressRow(), FakeBusinessContext::BUSINESS_ID)->id)
            ->toBe(AddressFixtures::ADDRESS_ID);
    });

    it('reads the owner back as the uuid it was handed, never as the column', function () {
        $address = $this->mapper->toEntity(addressRow(), FakeBusinessContext::BUSINESS_ID);

        expect($address->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($address->ownerId)->not->toBe((string) AddressFixtures::OWNER_KEY);
    });

    it('reads the state back as the uuid off the eager loaded relation', function () {
        $address = $this->mapper->toEntity(addressRow(), FakeBusinessContext::BUSINESS_ID);

        expect($address->stateId())->toBe(AddressFixtures::STATE_ID)
            ->and($address->stateId())->not->toBe((string) AddressFixtures::STATE_KEY);
    });

    it('reads a row with no state as an address with none', function () {
        $address = $this->mapper->toEntity(
            addressRow(['state_id' => null], withState: false),
            FakeBusinessContext::BUSINESS_ID,
        );

        expect($address->stateId())->toBeNull();
    });

    it('reads the owner kind back from the alias', function (string $alias, AddressOwnerType $ownerType) {
        expect($this->mapper->toEntity(addressRow(['addressable_type' => $alias]), FakeBusinessContext::BUSINESS_ID)
            ->ownerType)->toBe($ownerType);
    })->with([
        'business' => ['business', AddressOwnerType::Business],
        'staff member' => ['staff_member', AddressOwnerType::StaffMember],
    ]);

    it('rebuilds the postal facts the columns hold', function () {
        $address = $this->mapper->toEntity(addressRow(), FakeBusinessContext::BUSINESS_ID);

        expect($address->street())->toBe(AddressFixtures::STREET)
            ->and($address->city())->toBe(AddressFixtures::CITY)
            ->and($address->postalCode()->value)->toBe(AddressFixtures::POSTAL_CODE)
            ->and($address->country())->toBe(CountryCode::Mx);
    });

    it('rebuilds the point from the two decimal columns without drifting', function () {
        $coordinates = $this->mapper->toEntity(addressRow(), FakeBusinessContext::BUSINESS_ID)->coordinates();

        expect($coordinates?->latitude)->toBe(19.3627888)
            ->and($coordinates?->longitude)->toBe(-99.1768069);
    });

    it('reads an address as unpinned unless both halves of the point are present', function (array $overrides) {
        expect($this->mapper->toEntity(addressRow($overrides), FakeBusinessContext::BUSINESS_ID)->coordinates())
            ->toBeNull();
    })->with([
        'no latitude' => [['latitude' => null]],
        'no longitude' => [['longitude' => null]],
        'neither half' => [['latitude' => null, 'longitude' => null]],
    ]);

    it('restores a stored row even when it would fail the creation rules today', function () {
        expect($this->mapper->toEntity(addressRow(['street' => '']), FakeBusinessContext::BUSINESS_ID)->street())
            ->toBe('');
    });

    it('keeps the instant the row was created', function () {
        expect($this->mapper->toEntity(addressRow(), FakeBusinessContext::BUSINESS_ID)->createdAt)
            ->toEqual(AddressFixtures::now());
    });
});

it('survives a full round trip without losing a fact', function () {
    $address = AddressFixtures::address(
        ownerType: AddressOwnerType::StaffMember,
        street: 'Callejón del Ñandú 3',
        city: 'Querétaro',
        postalCode: '76000',
        country: CountryCode::Us,
        coordinates: AddressFixtures::coordinates(latitude: -33.4488897, longitude: -70.6692655),
    );

    $attributes = $this->mapper->toAttributes($address, AddressFixtures::OWNER_KEY, AddressFixtures::STATE_KEY);

    $restored = $this->mapper->toEntity(
        addressRow([...$attributes, 'created_at' => AddressFixtures::now()]),
        FakeBusinessContext::BUSINESS_ID,
    );

    expect($restored->id)->toBe($address->id)
        ->and($restored->ownerType)->toBe($address->ownerType)
        ->and($restored->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
        ->and($restored->street())->toBe($address->street())
        ->and($restored->city())->toBe($address->city())
        ->and($restored->stateId())->toBe(AddressFixtures::STATE_ID)
        ->and($restored->postalCode()->value)->toBe($address->postalCode()->value)
        ->and($restored->country())->toBe($address->country())
        ->and($restored->coordinates()?->latitude)->toBe(-33.4488897)
        ->and($restored->coordinates()?->longitude)->toBe(-70.6692655)
        ->and($restored->createdAt)->toEqual($address->createdAt);
});
