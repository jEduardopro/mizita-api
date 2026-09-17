<?php

declare(strict_types=1);

use App\Domains\Addresses\Application\UseCases\ReplaceAddress;
use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\Entities\Address;
use App\Domains\Addresses\Exceptions\AddressCityCannotBeCleared;
use App\Domains\Addresses\Exceptions\AddressPostalCodeCannotBeCleared;
use App\Domains\Addresses\Exceptions\InvalidAddressCity;
use App\Domains\Addresses\Exceptions\InvalidAddressPostalCode;
use App\Domains\Addresses\Exceptions\InvalidAddressStreet;
use App\Domains\Addresses\Exceptions\InvalidCoordinates;
use App\Domains\Addresses\Exceptions\UnsupportedCountry;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Businesses\Contracts\BusinessAddressBook;
use App\Domains\Businesses\Infrastructure\Gateways\AddressesBusinessAddressBook;
use App\Domains\Businesses\ValueObjects\BusinessAddressSnapshot;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Addresses\AddressFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

function businessAddressSnapshot(
    string $street = AddressFixtures::STREET,
    ?string $city = AddressFixtures::CITY,
    ?string $stateId = AddressFixtures::STATE_ID,
    ?string $postalCode = AddressFixtures::POSTAL_CODE,
    string $countryCode = 'MX',
    ?string $latitude = null,
    ?string $longitude = null,
): BusinessAddressSnapshot {
    return new BusinessAddressSnapshot(
        street: $street,
        city: $city,
        stateId: $stateId,
        postalCode: $postalCode,
        countryCode: $countryCode,
        latitude: $latitude,
        longitude: $longitude,
    );
}

function businessStreetOnlyAddress(): Address
{
    return Address::restore(
        id: AddressFixtures::ADDRESS_ID,
        ownerType: AddressOwnerType::Business,
        ownerId: FakeBusinessContext::BUSINESS_ID,
        street: AddressFixtures::STREET,
        city: null,
        stateId: null,
        postalCode: null,
        country: CountryCode::Mx,
        coordinates: null,
        createdAt: AddressFixtures::now(),
    );
}

beforeEach(function () {
    $this->addresses = Mockery::mock(AddressRepository::class);

    $this->addressBook = new AddressesBusinessAddressBook(
        $this->addresses,
        new ReplaceAddress(
            $this->addresses,
            new FixedIdGenerator(AddressFixtures::GENERATED_ADDRESS_ID),
            new FakeClock(AddressFixtures::now()),
        ),
    );

    $this->read = fn (): ?BusinessAddressSnapshot => $this->addressBook->forBusiness(FakeBusinessContext::BUSINESS_ID);

    $this->replace = fn (BusinessAddressSnapshot $address): mixed => $this->addressBook->replaceForBusiness(
        FakeBusinessContext::BUSINESS_ID,
        $address,
    );
});

describe('reading the address on file', function () {
    it('answers with nothing when the business never filed an address', function () {
        $this->addresses->shouldReceive('findForOwner')->once()
            ->with(AddressOwnerType::Business, FakeBusinessContext::BUSINESS_ID)
            ->andReturnNull();

        expect(($this->read)())->toBeNull();
    });

    it('translates the stored address into the snapshot the business domain reads', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(AddressFixtures::address());

        $snapshot = ($this->read)();

        expect($snapshot)->toBeInstanceOf(BusinessAddressSnapshot::class)
            ->and($snapshot->street)->toBe(AddressFixtures::STREET)
            ->and($snapshot->city)->toBe(AddressFixtures::CITY)
            ->and($snapshot->stateId)->toBe(AddressFixtures::STATE_ID)
            ->and($snapshot->postalCode)->toBe(AddressFixtures::POSTAL_CODE)
            ->and($snapshot->countryCode)->toBe('MX');
    });

    it('hands back a street only address with the city and the postal code null', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(businessStreetOnlyAddress());

        $snapshot = ($this->read)();

        expect($snapshot->street)->toBe(AddressFixtures::STREET)
            ->and($snapshot->city)->toBeNull()
            ->and($snapshot->stateId)->toBeNull()
            ->and($snapshot->postalCode)->toBeNull()
            ->and($snapshot->countryCode)->toBe('MX');
    });

    it('asks for the address under the business uuid, never an internal key', function () {
        $ownerId = null;

        $this->addresses->shouldReceive('findForOwner')->once()
            ->with(AddressOwnerType::Business, Mockery::capture($ownerId))
            ->andReturnNull();

        ($this->read)();

        expect($ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->toBeString()
            ->and(is_numeric($ownerId))->toBeFalse();
    });

    it('carries the state uuid across the port', function () {
        $this->addresses->shouldReceive('findForOwner')->once()
            ->andReturn(AddressFixtures::address(stateId: AddressFixtures::SECOND_STATE_ID));

        expect(($this->read)()->stateId)->toBe(AddressFixtures::SECOND_STATE_ID);
    });

    it('leaves both coordinates null for an address nobody pinned', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(AddressFixtures::address());

        $snapshot = ($this->read)();

        expect($snapshot->latitude)->toBeNull()
            ->and($snapshot->longitude)->toBeNull();
    });

    it('writes pinned coordinates as fixed scale decimal strings', function () {
        $this->addresses->shouldReceive('findForOwner')->once()
            ->andReturn(AddressFixtures::address(coordinates: AddressFixtures::coordinates()));

        $snapshot = ($this->read)();

        expect($snapshot->latitude)->toBe('19.3627888')
            ->and($snapshot->longitude)->toBe('-99.1768069');
    });

    it('pads a whole degree out to the scale the column stores', function () {
        $this->addresses->shouldReceive('findForOwner')->once()
            ->andReturn(AddressFixtures::address(coordinates: AddressFixtures::coordinates(19.0, -99.0)));

        $snapshot = ($this->read)();

        expect($snapshot->latitude)->toBe('19.0000000')
            ->and($snapshot->longitude)->toBe('-99.0000000');
    });
});

describe('filing the address a business submitted', function () {
    it('files a new address against the business when it had none', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(businessAddressSnapshot());

        expect($saved)->toBeInstanceOf(Address::class)
            ->and($saved->id)->toBe(AddressFixtures::GENERATED_ADDRESS_ID)
            ->and($saved->ownerType)->toBe(AddressOwnerType::Business)
            ->and($saved->ownerId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($saved->street())->toBe(AddressFixtures::STREET)
            ->and($saved->city())->toBe(AddressFixtures::CITY)
            ->and($saved->stateId())->toBe(AddressFixtures::STATE_ID)
            ->and($saved->postalCode()->value)->toBe(AddressFixtures::POSTAL_CODE)
            ->and($saved->country())->toBe(CountryCode::Mx)
            ->and($saved->createdAt)->toEqual(AddressFixtures::now());
    });

    it('relocates the address the business already had rather than filing a second one', function () {
        $existing = AddressFixtures::address();

        $this->addresses->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->addresses->shouldReceive('save')->once()->with($existing);

        ($this->replace)(businessAddressSnapshot(street: 'Calle Madero 12', city: 'Puebla'));

        expect($existing->street())->toBe('Calle Madero 12')
            ->and($existing->city())->toBe('Puebla')
            ->and($existing->id)->toBe(AddressFixtures::ADDRESS_ID);
    });

    it('files an address that carries a street and nothing else', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(businessAddressSnapshot(city: null, stateId: null, postalCode: null));

        expect($saved->street())->toBe(AddressFixtures::STREET)
            ->and($saved->city())->toBeNull()
            ->and($saved->stateId())->toBeNull()
            ->and($saved->postalCode())->toBeNull();
    });

    it('files an address with no postal code when the client left the box empty', function (?string $postalCode) {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(businessAddressSnapshot(postalCode: $postalCode));

        expect($saved->postalCode())->toBeNull();
    })->with([
        'no postal code at all' => null,
        'an empty one' => '',
        'spaces' => '   ',
    ]);

    it('files an address with no city when the client left the box empty', function (?string $city) {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(businessAddressSnapshot(city: $city));

        expect($saved->city())->toBeNull();
    })->with([
        'no city at all' => null,
        'an empty one' => '',
        'a tab' => "\t",
    ]);

    it('fills a city the address never carried, because only a value on file is protected', function () {
        $existing = businessStreetOnlyAddress();

        $this->addresses->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->addresses->shouldReceive('save')->once();

        ($this->replace)(businessAddressSnapshot(city: 'Puebla', postalCode: '72000'));

        expect($existing->city())->toBe('Puebla')
            ->and($existing->postalCode()?->value)->toBe('72000');
    });

    it('pins the address when the snapshot carries both coordinates', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(businessAddressSnapshot(latitude: '19.3627888', longitude: '-99.1768069'));

        expect($saved->coordinates()?->latitude)->toBe(19.3627888)
            ->and($saved->coordinates()?->longitude)->toBe(-99.1768069);
    });

    it('unpins the address the business had pinned when the snapshot carries no coordinates', function () {
        $existing = AddressFixtures::address(coordinates: AddressFixtures::coordinates());

        $this->addresses->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->addresses->shouldReceive('save')->once();

        ($this->replace)(businessAddressSnapshot());

        expect($existing->coordinates())->toBeNull();
    });

    it('accepts a country code however the client cased or padded it', function (string $countryCode) {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(businessAddressSnapshot(countryCode: $countryCode));

        expect($saved->country())->toBe(CountryCode::Mx);
    })->with([
        'upper case' => 'MX',
        'lower case' => 'mx',
        'padded' => '  Mx  ',
    ]);

    it('files an address with no state, which is what a country without one submits', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(businessAddressSnapshot(stateId: null));

        expect($saved->stateId())->toBeNull();
    });

    it('keeps the accents the street and the city were written with', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(businessAddressSnapshot(street: 'Callejón del Ñandú 3', city: 'Cañadas de Obregón'));

        expect($saved->street())->toBe('Callejón del Ñandú 3')
            ->and($saved->city())->toBe('Cañadas de Obregón');
    });
});

describe('refusing half a point', function () {
    it('refuses a pair with only one half filled, and writes nothing', function (?string $latitude, ?string $longitude) {
        $this->addresses->shouldNotReceive('save');

        expect(fn () => ($this->replace)(businessAddressSnapshot(latitude: $latitude, longitude: $longitude)))
            ->toThrow(InvalidCoordinates::class);
    })->with([
        'latitude alone' => ['19.3627888', null],
        'longitude alone' => [null, '-99.1768069'],
    ]);

    it('quotes the half it was given, so a log says which half was missing', function () {
        $this->addresses->shouldNotReceive('save');

        expect(fn () => ($this->replace)(businessAddressSnapshot(latitude: '19.3627888')))
            ->toThrow(InvalidCoordinates::class, 'The latitude [19.3627888] was given without a longitude.')
            ->and(fn () => ($this->replace)(businessAddressSnapshot(longitude: '-99.1768069')))
            ->toThrow(InvalidCoordinates::class, 'The longitude [-99.1768069] was given without a latitude.');
    });

    it('refuses half a point as a domain failure the responder can classify', function () {
        $this->addresses->shouldNotReceive('save');

        try {
            ($this->replace)(businessAddressSnapshot(latitude: '19.3627888'));
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown->errorCode())->toBe('invalid_coordinates')
            ->and($thrown->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('refuses half a point before it even reads the address on file', function () {
        $this->addresses->shouldNotReceive('findForOwner');
        $this->addresses->shouldNotReceive('save');

        expect(fn () => ($this->replace)(businessAddressSnapshot(longitude: '-99.1768069')))
            ->toThrow(InvalidCoordinates::class);
    });

    it('raises no warning while refusing a half filled pair', function () {
        $this->addresses->shouldNotReceive('save');

        $raised = [];

        set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
            $raised[] = $message;

            return true;
        });

        try {
            ($this->replace)(businessAddressSnapshot(latitude: '19.3627888'));
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        } finally {
            restore_error_handler();
        }

        expect($raised)->toBe([])
            ->and($thrown)->toBeInstanceOf(InvalidCoordinates::class);
    });

    it('still unpins the address when neither half was sent', function () {
        $existing = AddressFixtures::address(coordinates: AddressFixtures::coordinates());

        $this->addresses->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->addresses->shouldReceive('save')->once();

        ($this->replace)(businessAddressSnapshot(latitude: null, longitude: null));

        expect($existing->coordinates())->toBeNull();
    });
});

describe('refusing an address the neighbour will not take', function () {
    it('refuses a country the platform does not operate in before touching the repository', function () {
        $this->addresses->shouldNotReceive('findForOwner');
        $this->addresses->shouldNotReceive('save');

        expect(fn () => ($this->replace)(businessAddressSnapshot(countryCode: 'FR')))
            ->toThrow(UnsupportedCountry::class);
    });

    it('refuses a postal code the domain cannot read before touching the repository', function (string $postalCode) {
        $this->addresses->shouldNotReceive('findForOwner');
        $this->addresses->shouldNotReceive('save');

        expect(fn () => ($this->replace)(businessAddressSnapshot(postalCode: $postalCode)))
            ->toThrow(InvalidAddressPostalCode::class);
    })->with([
        'letters' => 'C1234',
        'too short' => '123',
        'too long' => '12345678901',
    ]);

    it('files nothing at all when a business with no address on file sends a blank street', function (string $street) {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->addresses->shouldNotReceive('save');

        expect(($this->replace)(businessAddressSnapshot(street: $street, city: null, postalCode: null)))->toBeNull();
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a tab' => "\t",
    ]);

    it('refuses to blank the street of an address already on file, because this form deletes none', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(AddressFixtures::address());
        $this->addresses->shouldNotReceive('save');

        expect(fn () => ($this->replace)(businessAddressSnapshot(street: '   ')))
            ->toThrow(InvalidAddressStreet::class);
    });

    it('refuses to clear a city the address already carries', function (?string $city) {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(AddressFixtures::address());
        $this->addresses->shouldNotReceive('save');

        $refusal = null;

        try {
            ($this->replace)(businessAddressSnapshot(city: $city));
        } catch (Throwable $escaped) {
            $refusal = $escaped;
        }

        expect($refusal)->toBeInstanceOf(AddressCityCannotBeCleared::class)
            ->and($refusal->errorCode())->toBe('address_city_cannot_be_cleared')
            ->and($refusal->kind())->toBe(DomainFailureKind::Conflict);
    })->with([
        'no city at all' => null,
        'an empty one' => '',
        'spaces' => '   ',
    ]);

    it('refuses to clear a postal code the address already carries', function (?string $postalCode) {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(AddressFixtures::address());
        $this->addresses->shouldNotReceive('save');

        $refusal = null;

        try {
            ($this->replace)(businessAddressSnapshot(postalCode: $postalCode));
        } catch (Throwable $escaped) {
            $refusal = $escaped;
        }

        expect($refusal)->toBeInstanceOf(AddressPostalCodeCannotBeCleared::class)
            ->and($refusal->errorCode())->toBe('address_postal_code_cannot_be_cleared')
            ->and($refusal->kind())->toBe(DomainFailureKind::Conflict);
    })->with([
        'no postal code at all' => null,
        'an empty one' => '',
        'spaces' => '   ',
    ]);

    it('names the street first when the whole block arrived blank', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(AddressFixtures::address());
        $this->addresses->shouldNotReceive('save');

        expect(fn () => ($this->replace)(businessAddressSnapshot(street: '', city: '', postalCode: '')))
            ->toThrow(InvalidAddressStreet::class);
    });

    it('leaves the address on file untouched when it refuses the one submitted', function () {
        $existing = AddressFixtures::address();

        $this->addresses->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->addresses->shouldNotReceive('save');

        try {
            ($this->replace)(businessAddressSnapshot(street: 'Calle Madero 12', city: null));
        } catch (AddressCityCannotBeCleared) {
        }

        expect($existing->street())->toBe(AddressFixtures::STREET)
            ->and($existing->city())->toBe(AddressFixtures::CITY);
    });

    it('refuses a city longer than the column, whether or not one is on file', function () {
        $this->addresses->shouldReceive('findForOwner')->andReturnNull();
        $this->addresses->shouldNotReceive('save');

        expect(fn () => ($this->replace)(businessAddressSnapshot(city: str_repeat('a', 121))))
            ->toThrow(InvalidAddressCity::class);
    });
});

describe('the rollback contract', function () {
    it('hands nothing back, so no use case response can cross the port', function () {
        $this->addresses->shouldReceive('findForOwner')->andReturnNull();
        $this->addresses->shouldReceive('save')->once();

        expect(($this->replace)(businessAddressSnapshot()))->toBeNull();
    });

    it('never declares a use case response on the port', function () {
        $returnTypes = array_map(
            static fn (ReflectionMethod $method): string => (string) $method->getReturnType(),
            (new ReflectionClass(BusinessAddressBook::class))->getMethods(),
        );

        expect($returnTypes)->not->toContain(UseCaseResponse::class)
            ->and($returnTypes)->not->toContain('?'.UseCaseResponse::class);
    });

    it('declares void on every write the port exposes', function (string $method) {
        expect((string) (new ReflectionMethod(BusinessAddressBook::class, $method))->getReturnType())->toBe('void');
    })->with(['replaceForBusiness']);

    it('exposes no way to delete the address a business filed', function () {
        expect(method_exists(BusinessAddressBook::class, 'removeForBusiness'))->toBeFalse()
            ->and(method_exists(AddressesBusinessAddressBook::class, 'removeForBusiness'))->toBeFalse();
    });

    it('rethrows the neighbour refusal itself, so the surrounding transaction rolls back', function () {
        $this->addresses->shouldReceive('findForOwner')->andReturn(AddressFixtures::address());
        $this->addresses->shouldNotReceive('save');

        try {
            ($this->replace)(businessAddressSnapshot(street: '   '));
            $thrown = null;
        } catch (Throwable $escaped) {
            $thrown = $escaped;
        }

        expect($thrown)->toBeInstanceOf(InvalidAddressStreet::class)
            ->and($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown->errorCode())->toBe('invalid_address_street')
            ->and($thrown->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('lets an infrastructure error out untouched', function () {
        $bug = new RuntimeException('the addresses table is gone');

        $this->addresses->shouldReceive('findForOwner')->andReturnNull();
        $this->addresses->shouldReceive('save')->once()->andThrow($bug);

        expect(fn () => ($this->replace)(businessAddressSnapshot()))->toThrow($bug);
    });

    it('does not write when the read it depends on fails', function () {
        $bug = new RuntimeException('the read replica went away');

        $this->addresses->shouldReceive('findForOwner')->once()->andThrow($bug);
        $this->addresses->shouldNotReceive('save');

        expect(fn () => ($this->replace)(businessAddressSnapshot()))->toThrow($bug);
    });
});
