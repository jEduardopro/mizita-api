<?php

declare(strict_types=1);

use App\Domains\Addresses\Application\UseCases\ReplaceAddress;
use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\Contracts\StateCatalog;
use App\Domains\Addresses\Entities\Address;
use App\Domains\Addresses\Exceptions\AddressCityCannotBeCleared;
use App\Domains\Addresses\Exceptions\AddressPostalCodeCannotBeCleared;
use App\Domains\Addresses\Exceptions\InvalidAddressCity;
use App\Domains\Addresses\Exceptions\InvalidAddressPostalCode;
use App\Domains\Addresses\Exceptions\InvalidAddressStateName;
use App\Domains\Addresses\Exceptions\InvalidAddressStreet;
use App\Domains\Addresses\Exceptions\UnknownState;
use App\Domains\Addresses\Exceptions\UnsupportedCountry;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use App\Domains\Customers\Contracts\CustomerAddressBook;
use App\Domains\Customers\Infrastructure\Gateways\AddressesCustomerAddressBook;
use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Addresses\AddressFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

const CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c1';

function filedCustomerAddress(
    string $street = AddressFixtures::STREET,
    ?string $city = AddressFixtures::CITY,
    ?string $stateId = AddressFixtures::STATE_ID,
    ?string $postalCode = AddressFixtures::POSTAL_CODE,
    string $countryCode = 'MX',
    ?string $stateName = null,
): CustomerAddressSnapshot {
    return new CustomerAddressSnapshot(
        street: $street,
        city: $city,
        stateId: $stateId,
        postalCode: $postalCode,
        countryCode: $countryCode,
        stateName: $stateName,
    );
}

function customerAddressOnFile(?string $city = AddressFixtures::CITY, ?string $postalCode = AddressFixtures::POSTAL_CODE): Address
{
    return AddressFixtures::address(
        ownerType: AddressOwnerType::Customer,
        ownerId: CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID,
        city: $city,
        postalCode: $postalCode,
    );
}

beforeEach(function () {
    $this->addresses = Mockery::mock(AddressRepository::class);
    $this->states = Mockery::mock(StateCatalog::class);

    $this->addressBook = new AddressesCustomerAddressBook(
        $this->addresses,
        new ReplaceAddress(
            $this->addresses,
            $this->states,
            new FixedIdGenerator(AddressFixtures::GENERATED_ADDRESS_ID),
            new FakeClock(AddressFixtures::now()),
        ),
    );

    $this->read = fn (): ?CustomerAddressSnapshot => $this->addressBook->forCustomer(CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID);

    $this->replace = fn (CustomerAddressSnapshot $address): mixed => $this->addressBook->replaceForCustomer(
        CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID,
        $address,
    );

    $this->remove = fn (): mixed => $this->addressBook->removeForCustomer(CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID);
});

describe('reading the address a customer filed', function () {
    it('answers with nothing when the customer never filed an address', function () {
        $this->addresses->shouldReceive('findForOwner')->once()
            ->with(AddressOwnerType::Customer, CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID)
            ->andReturnNull();

        expect(($this->read)())->toBeNull();
    });

    it('translates the stored address into the snapshot the customers domain reads', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(customerAddressOnFile());

        $snapshot = ($this->read)();

        expect($snapshot)->toBeInstanceOf(CustomerAddressSnapshot::class)
            ->and($snapshot->street)->toBe(AddressFixtures::STREET)
            ->and($snapshot->city)->toBe(AddressFixtures::CITY)
            ->and($snapshot->stateId)->toBe(AddressFixtures::STATE_ID)
            ->and($snapshot->postalCode)->toBe(AddressFixtures::POSTAL_CODE)
            ->and($snapshot->countryCode)->toBe('MX');
    });

    it('hands the postal code across as the plain string, not the neighbour value object', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(customerAddressOnFile());

        expect(($this->read)()->postalCode)->toBeString();
    });

    it('hands back a street only address with every other postal fact null', function () {
        $this->addresses->shouldReceive('findForOwner')->once()
            ->andReturn(customerAddressOnFile(city: null, postalCode: null));

        $snapshot = ($this->read)();

        expect($snapshot->street)->toBe(AddressFixtures::STREET)
            ->and($snapshot->city)->toBeNull()
            ->and($snapshot->postalCode)->toBeNull()
            ->and($snapshot->countryCode)->toBe('MX');
    });

    it('asks for the address under the customer uuid, never an internal key', function () {
        $ownerId = null;

        $this->addresses->shouldReceive('findForOwner')->once()
            ->with(AddressOwnerType::Customer, Mockery::capture($ownerId))
            ->andReturnNull();

        ($this->read)();

        expect($ownerId)->toBe(CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID)
            ->toBeString()
            ->and($ownerId)->not->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and(is_numeric($ownerId))->toBeFalse();
    });

    it('carries the state uuid across the port', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(AddressFixtures::address(
            ownerType: AddressOwnerType::Customer,
            ownerId: CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID,
            stateId: AddressFixtures::SECOND_STATE_ID,
        ));

        expect(($this->read)()->stateId)->toBe(AddressFixtures::SECOND_STATE_ID);
    });

    it('carries the typed state name across the port', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(AddressFixtures::address(
            ownerType: AddressOwnerType::Customer,
            ownerId: CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID,
            stateId: null,
            stateName: 'Nuevo León',
        ));

        $snapshot = ($this->read)();

        expect($snapshot->stateName)->toBe('Nuevo León')
            ->and($snapshot->stateId)->toBeNull();
    });

    it('hands back no state name when the address has a catalogue state only', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(customerAddressOnFile());

        expect(($this->read)()->stateName)->toBeNull();
    });

    it('drops the coordinates, because a customer address is a postal fact and not a pin', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(AddressFixtures::address(
            ownerType: AddressOwnerType::Customer,
            ownerId: CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID,
            coordinates: AddressFixtures::coordinates(),
        ));

        $snapshot = ($this->read)();

        expect($snapshot->street)->toBe(AddressFixtures::STREET)
            ->and(property_exists($snapshot, 'latitude'))->toBeFalse()
            ->and(property_exists($snapshot, 'longitude'))->toBeFalse();
    });
});

describe('filing the address a customer submitted', function () {
    it('files a new address against the customer when it had none', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(filedCustomerAddress());

        expect($saved)->toBeInstanceOf(Address::class)
            ->and($saved->id)->toBe(AddressFixtures::GENERATED_ADDRESS_ID)
            ->and($saved->ownerType)->toBe(AddressOwnerType::Customer)
            ->and($saved->ownerId)->toBe(CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID)
            ->and($saved->street())->toBe(AddressFixtures::STREET)
            ->and($saved->city())->toBe(AddressFixtures::CITY)
            ->and($saved->stateId())->toBe(AddressFixtures::STATE_ID)
            ->and($saved->postalCode()?->value)->toBe(AddressFixtures::POSTAL_CODE)
            ->and($saved->country())->toBe(CountryCode::Mx)
            ->and($saved->createdAt)->toEqual(AddressFixtures::now());
    });

    it('relocates the address the customer already had rather than filing a second one', function () {
        $existing = customerAddressOnFile();

        $this->addresses->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->addresses->shouldReceive('save')->once()->with($existing);

        ($this->replace)(filedCustomerAddress(street: 'Calle Madero 12', city: 'Puebla'));

        expect($existing->id)->toBe(AddressFixtures::ADDRESS_ID)
            ->and($existing->street())->toBe('Calle Madero 12')
            ->and($existing->city())->toBe('Puebla');
    });

    it('files an address that carries a street and nothing else', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(filedCustomerAddress(city: null, stateId: null, postalCode: null));

        expect($saved->street())->toBe(AddressFixtures::STREET)
            ->and($saved->city())->toBeNull()
            ->and($saved->stateId())->toBeNull()
            ->and($saved->postalCode())->toBeNull();
    });

    it('files an address with no postal code when the customer left the box empty', function (?string $postalCode) {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(filedCustomerAddress(postalCode: $postalCode));

        expect($saved->postalCode())->toBeNull();
    })->with([
        'no postal code at all' => null,
        'an empty one' => '',
        'spaces' => '   ',
    ]);

    it('accepts a country code however the client cased or padded it', function (string $countryCode) {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(filedCustomerAddress(countryCode: $countryCode));

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

        ($this->replace)(filedCustomerAddress(stateId: null));

        expect($saved->stateId())->toBeNull();
    });

    it('files a typed state name and links the catalogue state it matches', function () {
        $this->states->shouldReceive('findActiveByNameOrCode')->once()
            ->with(CountryCode::Mx, 'Ciudad de Mexico')
            ->andReturn(AddressFixtures::state());
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(filedCustomerAddress(stateId: null, stateName: 'Ciudad de Mexico'));

        expect($saved->stateId())->toBe(AddressFixtures::STATE_ID)
            ->and($saved->stateName())->toBe('Ciudad de Mexico');
    });

    it('files a typed state name the catalogue does not know, with no state linked', function () {
        $this->states->shouldReceive('findActiveByNameOrCode')->once()->andReturnNull();
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(filedCustomerAddress(stateId: null, stateName: 'Atlantis'));

        expect($saved->stateId())->toBeNull()
            ->and($saved->stateName())->toBe('Atlantis');
    });

    it('keeps the accents the street and the city were written with', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(filedCustomerAddress(street: 'Callejón del Ñandú 3', city: 'Cañadas de Obregón'));

        expect($saved->street())->toBe('Callejón del Ñandú 3')
            ->and($saved->city())->toBe('Cañadas de Obregón');
    });

    it('never pins the address, because the customer form submits no coordinates', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();

        $saved = null;
        $this->addresses->shouldReceive('save')->once()->with(Mockery::capture($saved));

        ($this->replace)(filedCustomerAddress());

        expect($saved->coordinates())->toBeNull();
    });

    it('unpins an address that was pinned, since the customer snapshot carries no point', function () {
        $existing = AddressFixtures::address(
            ownerType: AddressOwnerType::Customer,
            ownerId: CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID,
            coordinates: AddressFixtures::coordinates(),
        );

        $this->addresses->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->addresses->shouldReceive('save')->once();

        ($this->replace)(filedCustomerAddress());

        expect($existing->coordinates())->toBeNull();
    });
});

describe('refusing an address the neighbour will not take', function () {
    it('refuses a country the platform does not operate in before touching the repository', function () {
        $this->addresses->shouldNotReceive('findForOwner');
        $this->addresses->shouldNotReceive('save');

        $refusal = null;

        try {
            ($this->replace)(filedCustomerAddress(countryCode: 'FR'));
        } catch (Throwable $escaped) {
            $refusal = $escaped;
        }

        expect($refusal)->toBeInstanceOf(UnsupportedCountry::class)
            ->and($refusal->errorCode())->toBe('unsupported_country')
            ->and($refusal->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('refuses a postal code the neighbour cannot read before touching the repository', function (string $postalCode) {
        $this->addresses->shouldNotReceive('findForOwner');
        $this->addresses->shouldNotReceive('save');

        $refusal = null;

        try {
            ($this->replace)(filedCustomerAddress(postalCode: $postalCode));
        } catch (Throwable $escaped) {
            $refusal = $escaped;
        }

        expect($refusal)->toBeInstanceOf(InvalidAddressPostalCode::class)
            ->and($refusal->errorCode())->toBe('invalid_address_postal_code')
            ->and($refusal->kind())->toBe(DomainFailureKind::Invalid);
    })->with([
        'letters' => 'C1234',
        'too short' => '123',
        'too long' => '12345678901',
    ]);

    it('files nothing at all when a customer with no address on file sends a blank street', function (string $street) {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->addresses->shouldNotReceive('save');

        expect(($this->replace)(filedCustomerAddress(street: $street, city: null, postalCode: null)))->toBeNull();
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a tab' => "\t",
    ]);

    it('refuses to blank the street of an address already on file', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(customerAddressOnFile());
        $this->addresses->shouldNotReceive('save');

        $refusal = null;

        try {
            ($this->replace)(filedCustomerAddress(street: '   '));
        } catch (Throwable $escaped) {
            $refusal = $escaped;
        }

        expect($refusal)->toBeInstanceOf(InvalidAddressStreet::class)
            ->and($refusal->errorCode())->toBe('invalid_address_street')
            ->and($refusal->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('refuses a city longer than the column', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->addresses->shouldNotReceive('save');

        $refusal = null;

        try {
            ($this->replace)(filedCustomerAddress(city: str_repeat('a', 121)));
        } catch (Throwable $escaped) {
            $refusal = $escaped;
        }

        expect($refusal)->toBeInstanceOf(InvalidAddressCity::class)
            ->and($refusal->errorCode())->toBe('invalid_address_city')
            ->and($refusal->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('refuses to clear a city the address already carries', function (?string $city) {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(customerAddressOnFile());
        $this->addresses->shouldNotReceive('save');

        $refusal = null;

        try {
            ($this->replace)(filedCustomerAddress(city: $city));
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
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(customerAddressOnFile());
        $this->addresses->shouldNotReceive('save');

        $refusal = null;

        try {
            ($this->replace)(filedCustomerAddress(postalCode: $postalCode));
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

    it('lets a state nobody has on record out with the code the neighbour raised', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->addresses->shouldReceive('save')->once()
            ->andThrow(UnknownState::withId(AddressFixtures::SECOND_STATE_ID));

        $refusal = null;

        try {
            ($this->replace)(filedCustomerAddress(stateId: AddressFixtures::SECOND_STATE_ID));
        } catch (Throwable $escaped) {
            $refusal = $escaped;
        }

        expect($refusal)->toBeInstanceOf(UnknownState::class)
            ->and($refusal->errorCode())->toBe('unknown_state')
            ->and($refusal->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('refuses a typed state name longer than the column', function () {
        $this->states->shouldReceive('findActiveByNameOrCode')->andReturnNull();
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->addresses->shouldNotReceive('save');

        expect(fn () => ($this->replace)(filedCustomerAddress(stateId: null, stateName: str_repeat('a', 121))))
            ->toThrow(InvalidAddressStateName::class);
    });

    it('leaves the address on file untouched when it refuses the one submitted', function () {
        $existing = customerAddressOnFile();

        $this->addresses->shouldReceive('findForOwner')->once()->andReturn($existing);
        $this->addresses->shouldNotReceive('save');

        try {
            ($this->replace)(filedCustomerAddress(street: 'Calle Madero 12', city: null));
        } catch (AddressCityCannotBeCleared) {
        }

        expect($existing->street())->toBe(AddressFixtures::STREET)
            ->and($existing->city())->toBe(AddressFixtures::CITY);
    });
});

describe('deleting the address a customer filed', function () {
    it('deletes under the customer uuid, as a customer address', function () {
        $this->addresses->shouldReceive('deleteForOwner')->once()
            ->with(AddressOwnerType::Customer, CUSTOMER_ADDRESS_BOOK_CUSTOMER_ID);
        $this->addresses->shouldNotReceive('save');
        $this->addresses->shouldNotReceive('findForOwner');

        expect(($this->remove)())->toBeNull();
    });

    it('lets a repository error out untouched', function () {
        $bug = new RuntimeException('the addresses table is gone');

        $this->addresses->shouldReceive('deleteForOwner')->once()->andThrow($bug);

        expect(fn () => ($this->remove)())->toThrow($bug);
    });
});

describe('the rollback contract', function () {
    it('hands nothing back, so no use case response can cross the port', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->addresses->shouldReceive('save')->once();

        expect(($this->replace)(filedCustomerAddress()))->toBeNull();
    });

    it('never declares a use case response on the port', function () {
        $returnTypes = array_map(
            static fn (ReflectionMethod $method): string => (string) $method->getReturnType(),
            (new ReflectionClass(CustomerAddressBook::class))->getMethods(),
        );

        expect($returnTypes)->not->toContain(UseCaseResponse::class)
            ->and($returnTypes)->not->toContain('?'.UseCaseResponse::class);
    });

    it('declares void on every write the port exposes', function (string $method) {
        expect((string) (new ReflectionMethod(CustomerAddressBook::class, $method))->getReturnType())->toBe('void');
    })->with(['replaceForCustomer', 'removeForCustomer']);

    it('rethrows the neighbour refusal itself instead of answering with it', function () {
        $this->addresses->shouldReceive('findForOwner')->once()->andReturn(customerAddressOnFile());
        $this->addresses->shouldNotReceive('save');

        try {
            $answer = ($this->replace)(filedCustomerAddress(street: '   '));
            $thrown = null;
        } catch (Throwable $escaped) {
            $answer = 'nothing was answered';
            $thrown = $escaped;
        }

        expect($answer)->toBe('nothing was answered')
            ->and($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown)->toBeInstanceOf(InvalidAddressStreet::class);
    });

    it('lets an infrastructure error out untouched', function () {
        $bug = new RuntimeException('the addresses table is gone');

        $this->addresses->shouldReceive('findForOwner')->once()->andReturnNull();
        $this->addresses->shouldReceive('save')->once()->andThrow($bug);

        expect(fn () => ($this->replace)(filedCustomerAddress()))->toThrow($bug);
    });

    it('does not write when the read it depends on fails', function () {
        $bug = new RuntimeException('the read replica went away');

        $this->addresses->shouldReceive('findForOwner')->once()->andThrow($bug);
        $this->addresses->shouldNotReceive('save');

        expect(fn () => ($this->replace)(filedCustomerAddress()))->toThrow($bug);
    });
});
