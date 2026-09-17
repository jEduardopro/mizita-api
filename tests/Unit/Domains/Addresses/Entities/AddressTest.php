<?php

declare(strict_types=1);

use App\Domains\Addresses\Entities\Address;
use App\Domains\Addresses\Exceptions\AddressCityCannotBeCleared;
use App\Domains\Addresses\Exceptions\AddressPostalCodeCannotBeCleared;
use App\Domains\Addresses\Exceptions\InvalidAddressCity;
use App\Domains\Addresses\Exceptions\InvalidAddressStreet;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Addresses\ValueObjects\Coordinates;
use App\Domains\Addresses\ValueObjects\PostalCode;
use App\Shared\ValueObjects\CountryCode;
use Tests\Support\Addresses\AddressFixtures;
use Tests\Support\FakeBusinessContext;

function createAddress(
    string $street = AddressFixtures::STREET,
    ?string $city = AddressFixtures::CITY,
    ?string $stateId = AddressFixtures::STATE_ID,
    ?string $postalCode = AddressFixtures::POSTAL_CODE,
    CountryCode $country = CountryCode::Mx,
    ?Coordinates $coordinates = null,
    AddressOwnerType $ownerType = AddressOwnerType::Business,
    string $ownerId = FakeBusinessContext::BUSINESS_ID,
): Address {
    return Address::create(
        id: AddressFixtures::ADDRESS_ID,
        ownerType: $ownerType,
        ownerId: $ownerId,
        street: $street,
        city: $city,
        stateId: $stateId,
        postalCode: $postalCode === null ? null : PostalCode::restore($postalCode),
        country: $country,
        coordinates: $coordinates,
        now: AddressFixtures::now(),
    );
}

describe('creating an address', function () {
    it('holds every fact it was given, pinned to the owner that asked', function () {
        $address = createAddress(coordinates: AddressFixtures::coordinates());

        expect($address->id)->toBe(AddressFixtures::ADDRESS_ID)
            ->and($address->ownerType)->toBe(AddressOwnerType::Business)
            ->and($address->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($address->street())->toBe(AddressFixtures::STREET)
            ->and($address->city())->toBe(AddressFixtures::CITY)
            ->and($address->stateId())->toBe(AddressFixtures::STATE_ID)
            ->and($address->postalCode()->value)->toBe(AddressFixtures::POSTAL_CODE)
            ->and($address->country())->toBe(CountryCode::Mx)
            ->and($address->coordinates()?->latitude)->toBe(AddressFixtures::LATITUDE)
            ->and($address->createdAt)->toEqual(AddressFixtures::now());
    });

    it('names its owner by the uuid, never by a row number', function () {
        expect(createAddress(ownerId: FakeBusinessContext::BUSINESS_ID)->ownerId)
            ->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and(createAddress()->ownerId)->not->toBe((string) AddressFixtures::OWNER_KEY);
    });

    it('belongs to a staff member just as readily as to a business', function () {
        expect(createAddress(ownerType: AddressOwnerType::StaffMember)->ownerType)
            ->toBe(AddressOwnerType::StaffMember);
    });

    it('trims the street it was handed', function () {
        expect(createAddress(street: "  Calle Madero 12 \t ")->street())->toBe('Calle Madero 12');
    });

    it('trims the city it was handed', function () {
        expect(createAddress(city: '  Monterrey  ')->city())->toBe('Monterrey');
    });

    it('keeps the accents of a place instead of folding them', function () {
        $address = createAddress(street: 'Callejón del Ñandú 3', city: 'Querétaro');

        expect($address->street())->toBe('Callejón del Ñandú 3')
            ->and($address->city())->toBe('Querétaro');
    });

    it('rejects a street it cannot accept', function (string $street) {
        expect(fn () => createAddress(street: $street))->toThrow(InvalidAddressStreet::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
        'one past the maximum' => str_repeat('a', Address::MAXIMUM_STREET_LENGTH + 1),
    ]);

    it('rejects a city one past the maximum the column allows', function () {
        expect(fn () => createAddress(city: str_repeat('a', Address::MAXIMUM_CITY_LENGTH + 1)))
            ->toThrow(InvalidAddressCity::class);
    });

    it('is filed with nothing but a street when that is all anyone knows', function () {
        $address = createAddress(city: null, stateId: null, postalCode: null);

        expect($address->street())->toBe(AddressFixtures::STREET)
            ->and($address->city())->toBeNull()
            ->and($address->stateId())->toBeNull()
            ->and($address->postalCode())->toBeNull();
    });

    it('files a city that carries nothing as a city it does not have', function (string $city) {
        expect(createAddress(city: $city)->city())->toBeNull();
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
    ]);

    it('accepts a street exactly as long as the column allows', function () {
        expect(createAddress(street: str_repeat('a', Address::MAXIMUM_STREET_LENGTH))->street())
            ->toHaveLength(Address::MAXIMUM_STREET_LENGTH);
    });

    it('accepts a city exactly as long as the column allows', function () {
        expect(createAddress(city: str_repeat('a', Address::MAXIMUM_CITY_LENGTH))->city())
            ->toHaveLength(Address::MAXIMUM_CITY_LENGTH);
    });

    it('measures a street in characters, not in bytes', function () {
        $street = str_repeat('ñ', Address::MAXIMUM_STREET_LENGTH);

        expect(createAddress(street: $street)->street())->toBe($street);
    });

    it('measures what is left after trimming, not what it was handed', function () {
        $street = '  '.str_repeat('a', Address::MAXIMUM_STREET_LENGTH).'  ';

        expect(createAddress(street: $street)->street())->toHaveLength(Address::MAXIMUM_STREET_LENGTH);
    });

    it('accepts an address in a place with no state on record', function () {
        expect(createAddress(stateId: null)->stateId())->toBeNull();
    });

    it('starts unpinned when no point on the map was given', function () {
        expect(createAddress()->coordinates())->toBeNull();
    });

    it('accepts a country other than the default one', function () {
        expect(createAddress(country: CountryCode::Us)->country())->toBe(CountryCode::Us);
    });
});

describe('restoring an address from persistence', function () {
    it('skips the creation-time invariants a stored row has already passed', function () {
        $address = AddressFixtures::address(street: '', city: '');

        expect($address->street())->toBe('')
            ->and($address->city())->toBe('');
    });

    it('keeps a stored value exactly as the column holds it, padding included', function () {
        expect(AddressFixtures::address(street: '  Calle Madero 12  ')->street())
            ->toBe('  Calle Madero 12  ');
    });

    it('reads a row holding nothing but a street as an address with no city and no postal code', function () {
        $address = AddressFixtures::streetOnly();

        expect($address->street())->toBe(AddressFixtures::STREET)
            ->and($address->city())->toBeNull()
            ->and($address->stateId())->toBeNull()
            ->and($address->postalCode())->toBeNull();
    });
});

describe('relocating an address', function () {
    it('replaces every postal fact while keeping the identity it was created with', function () {
        $address = createAddress();

        $address->relocateTo(
            street: '  Paseo de la Reforma 222  ',
            city: '  Guadalajara  ',
            stateId: AddressFixtures::SECOND_STATE_ID,
            postalCode: PostalCode::restore('44100'),
            country: CountryCode::Us,
        );

        expect($address->street())->toBe('Paseo de la Reforma 222')
            ->and($address->city())->toBe('Guadalajara')
            ->and($address->stateId())->toBe(AddressFixtures::SECOND_STATE_ID)
            ->and($address->postalCode()->value)->toBe('44100')
            ->and($address->country())->toBe(CountryCode::Us)
            ->and($address->id)->toBe(AddressFixtures::ADDRESS_ID)
            ->and($address->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($address->createdAt)->toEqual(AddressFixtures::now());
    });

    it('drops the state when the new place has none', function () {
        $address = createAddress();

        $address->relocateTo(
            street: 'Calle Madero 12',
            city: 'Mérida',
            stateId: null,
            postalCode: PostalCode::restore('97000'),
            country: CountryCode::Mx,
        );

        expect($address->stateId())->toBeNull();
    });

    it('leaves the point on the map alone, because moving is not repinning', function () {
        $address = createAddress(coordinates: AddressFixtures::coordinates());

        $address->relocateTo(
            street: 'Calle Madero 12',
            city: 'Mérida',
            stateId: null,
            postalCode: PostalCode::restore('97000'),
            country: CountryCode::Mx,
        );

        expect($address->coordinates()?->latitude)->toBe(AddressFixtures::LATITUDE);
    });

    it('refuses to relocate to a street it would have refused at creation', function (string $street) {
        expect(fn () => createAddress()->relocateTo(
            street: $street,
            city: 'Mérida',
            stateId: null,
            postalCode: PostalCode::restore('97000'),
            country: CountryCode::Mx,
        ))->toThrow(InvalidAddressStreet::class);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'one past the maximum' => str_repeat('a', Address::MAXIMUM_STREET_LENGTH + 1),
    ]);

    it('refuses to relocate to a city one past the maximum the column allows', function () {
        expect(fn () => createAddress()->relocateTo(
            street: 'Calle Madero 12',
            city: str_repeat('a', Address::MAXIMUM_CITY_LENGTH + 1),
            stateId: null,
            postalCode: PostalCode::restore('97000'),
            country: CountryCode::Mx,
        ))->toThrow(InvalidAddressCity::class);
    });

    it('fills in a city it never had', function () {
        $address = createAddress(city: null, stateId: null, postalCode: null);

        $address->relocateTo(
            street: AddressFixtures::STREET,
            city: '  Mérida  ',
            stateId: AddressFixtures::SECOND_STATE_ID,
            postalCode: PostalCode::restore('97000'),
            country: CountryCode::Mx,
        );

        expect($address->city())->toBe('Mérida')
            ->and($address->postalCode()?->value)->toBe('97000');
    });

    it('stays without a city when it never had one and none was offered', function (?string $city) {
        $address = createAddress(city: null, stateId: null, postalCode: null);

        $address->relocateTo(
            street: 'Calle Madero 12',
            city: $city,
            stateId: null,
            postalCode: null,
            country: CountryCode::Mx,
        );

        expect($address->city())->toBeNull()
            ->and($address->postalCode())->toBeNull()
            ->and($address->street())->toBe('Calle Madero 12');
    })->with([
        'nothing at all' => null,
        'empty' => '',
        'spaces' => '   ',
    ]);

    it('refuses to empty a city it already has', function (?string $city) {
        expect(fn () => createAddress()->relocateTo(
            street: 'Calle Madero 12',
            city: $city,
            stateId: null,
            postalCode: PostalCode::restore('97000'),
            country: CountryCode::Mx,
        ))->toThrow(AddressCityCannotBeCleared::class);
    })->with([
        'nothing at all' => null,
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
    ]);

    it('refuses to empty a postal code it already has', function () {
        expect(fn () => createAddress()->relocateTo(
            street: 'Calle Madero 12',
            city: 'Mérida',
            stateId: null,
            postalCode: null,
            country: CountryCode::Mx,
        ))->toThrow(AddressPostalCodeCannotBeCleared::class);
    });

    it('replaces a postal code it already has with another one', function () {
        $address = createAddress();

        $address->relocateTo(
            street: 'Calle Madero 12',
            city: 'Mérida',
            stateId: null,
            postalCode: PostalCode::restore('97000'),
            country: CountryCode::Mx,
        );

        expect($address->postalCode()?->value)->toBe('97000');
    });

    it('leaves the old address untouched when the new one is refused', function () {
        $address = createAddress();

        try {
            $address->relocateTo(
                street: 'Paseo de la Reforma 222',
                city: '',
                stateId: null,
                postalCode: PostalCode::restore('97000'),
                country: CountryCode::Mx,
            );
        } catch (AddressCityCannotBeCleared) {
        }

        expect($address->street())->toBe(AddressFixtures::STREET)
            ->and($address->city())->toBe(AddressFixtures::CITY)
            ->and($address->stateId())->toBe(AddressFixtures::STATE_ID)
            ->and($address->postalCode()?->value)->toBe(AddressFixtures::POSTAL_CODE);
    });

    it('leaves the old address untouched when the postal code it holds would be emptied', function () {
        $address = createAddress();

        try {
            $address->relocateTo(
                street: 'Paseo de la Reforma 222',
                city: 'Mérida',
                stateId: null,
                postalCode: null,
                country: CountryCode::Mx,
            );
        } catch (AddressPostalCodeCannotBeCleared) {
        }

        expect($address->street())->toBe(AddressFixtures::STREET)
            ->and($address->city())->toBe(AddressFixtures::CITY)
            ->and($address->postalCode()?->value)->toBe(AddressFixtures::POSTAL_CODE);
    });

    it('still lets the state go, because only the written fields are protected', function () {
        $address = createAddress();

        $address->relocateTo(
            street: 'Calle Madero 12',
            city: 'Mérida',
            stateId: null,
            postalCode: PostalCode::restore('97000'),
            country: CountryCode::Mx,
        );

        $address->unpin();

        expect($address->stateId())->toBeNull()
            ->and($address->coordinates())->toBeNull();
    });
});

describe('pinning an address on the map', function () {
    it('takes a point it had none for', function () {
        $address = createAddress();

        $address->pinAt(AddressFixtures::coordinates());

        expect($address->coordinates()?->latitude)->toBe(AddressFixtures::LATITUDE)
            ->and($address->coordinates()?->longitude)->toBe(AddressFixtures::LONGITUDE);
    });

    it('replaces a point it already had', function () {
        $address = createAddress(coordinates: AddressFixtures::coordinates());

        $address->pinAt(AddressFixtures::coordinates(latitude: 25.6866142, longitude: -100.3161126));

        expect($address->coordinates()?->latitude)->toBe(25.6866142);
    });

    it('forgets the point when it is unpinned', function () {
        $address = createAddress(coordinates: AddressFixtures::coordinates());

        $address->unpin();

        expect($address->coordinates())->toBeNull();
    });

    it('stays unpinned when it is unpinned twice', function () {
        $address = createAddress();

        $address->unpin();
        $address->unpin();

        expect($address->coordinates())->toBeNull();
    });
});
