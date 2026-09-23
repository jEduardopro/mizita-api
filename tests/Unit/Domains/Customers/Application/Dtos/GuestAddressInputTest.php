<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\GuestAddressInput;
use App\Domains\Customers\Exceptions\InvalidCustomerAddress;
use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Customers\CustomerFixtures;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function customerGuestAddressPayload(array $overrides = []): array
{
    return [
        'street' => CustomerFixtures::STREET,
        'city' => CustomerFixtures::CITY,
        'state' => CustomerFixtures::STATE_NAME,
        'postal_code' => CustomerFixtures::POSTAL_CODE,
        'country_code' => CustomerFixtures::COUNTRY_CODE,
        ...$overrides,
    ];
}

describe('reading a payload', function () {
    it('assembles itself from the address block a visitor sent', function () {
        $address = GuestAddressInput::fromPayload(customerGuestAddressPayload());

        expect($address)->toBeInstanceOf(GuestAddressInput::class)
            ->and($address?->street)->toBe(CustomerFixtures::STREET)
            ->and($address?->city)->toBe(CustomerFixtures::CITY)
            ->and($address?->stateName)->toBe(CustomerFixtures::STATE_NAME)
            ->and($address?->postalCode)->toBe(CustomerFixtures::POSTAL_CODE)
            ->and($address?->countryCode)->toBe(CustomerFixtures::COUNTRY_CODE);
    });

    it('reads no address at all when the block is missing or is not a block', function (mixed $payload) {
        expect(GuestAddressInput::fromPayload($payload))->toBeNull();
    })->with([
        'null' => null,
        'an empty array' => [[]],
        'a string' => 'Av. Reforma 123',
        'a number' => 7,
    ]);

    it('survives a block with every key missing, reading the street and country as empty', function () {
        $address = GuestAddressInput::fromPayload(['unexpected' => true]);

        expect($address?->street)->toBe('')
            ->and($address?->city)->toBeNull()
            ->and($address?->stateName)->toBeNull()
            ->and($address?->postalCode)->toBeNull()
            ->and($address?->countryCode)->toBe('');
    });

    it('turns a value of the wrong type into the empty one, rather than a php error', function () {
        $address = GuestAddressInput::fromPayload([
            'street' => ['Av. Reforma'],
            'city' => 7,
            'state' => false,
            'postal_code' => 6600,
            'country_code' => ['MX'],
        ]);

        expect($address?->street)->toBe('')
            ->and($address?->city)->toBeNull()
            ->and($address?->stateName)->toBeNull()
            ->and($address?->postalCode)->toBeNull()
            ->and($address?->countryCode)->toBe('');
    });

    it('reads a blank optional as nothing at all', function (mixed $blank) {
        $address = GuestAddressInput::fromPayload(customerGuestAddressPayload([
            'city' => $blank,
            'state' => $blank,
            'postal_code' => $blank,
        ]));

        expect($address?->city)->toBeNull()
            ->and($address?->stateName)->toBeNull()
            ->and($address?->postalCode)->toBeNull();
    })->with([
        'an empty string' => '',
        'spaces' => '   ',
        'null' => null,
    ]);
});

describe('validating', function () {
    it('accepts a well formed address', function () {
        expect(fn () => GuestAddressInput::fromPayload(customerGuestAddressPayload())?->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts an address carrying nothing but a street and a country', function () {
        expect(fn () => GuestAddressInput::fromPayload([
            'street' => CustomerFixtures::STREET,
            'country_code' => 'MX',
        ])?->validate())->not->toThrow(Throwable::class);
    });

    it('accepts every field at exactly its boundary', function () {
        expect(fn () => (new GuestAddressInput(
            street: str_repeat('ñ', GuestAddressInput::MAXIMUM_STREET_LENGTH),
            city: str_repeat('é', GuestAddressInput::MAXIMUM_CITY_LENGTH),
            stateName: str_repeat('ó', GuestAddressInput::MAXIMUM_STATE_NAME_LENGTH),
            postalCode: str_repeat('1', GuestAddressInput::MAXIMUM_POSTAL_CODE_LENGTH),
            countryCode: 'MX',
        ))->validate())->not->toThrow(Throwable::class);
    });

    it('accepts the shortest postal code it allows', function () {
        expect(fn () => GuestAddressInput::fromPayload(customerGuestAddressPayload([
            'postal_code' => str_repeat('1', GuestAddressInput::MINIMUM_POSTAL_CODE_LENGTH),
        ]))?->validate())->not->toThrow(Throwable::class);
    });

    it('measures a field after trimming it', function () {
        expect(fn () => GuestAddressInput::fromPayload(customerGuestAddressPayload([
            'street' => '  '.str_repeat('a', GuestAddressInput::MAXIMUM_STREET_LENGTH).'  ',
            'country_code' => ' MX ',
        ]))?->validate())->not->toThrow(Throwable::class);
    });

    it('refuses an address block the form request would have rejected', function (array $overrides) {
        expect(fn () => GuestAddressInput::fromPayload(customerGuestAddressPayload($overrides))?->validate())
            ->toThrow(InvalidCustomerAddress::class);
    })->with([
        'no street' => [['street' => null]],
        'a blank street' => [['street' => '   ']],
        'a street past the maximum' => [['street' => str_repeat('a', GuestAddressInput::MAXIMUM_STREET_LENGTH + 1)]],
        'a city past the maximum' => [['city' => str_repeat('a', GuestAddressInput::MAXIMUM_CITY_LENGTH + 1)]],
        'a state past the maximum' => [['state' => str_repeat('a', GuestAddressInput::MAXIMUM_STATE_NAME_LENGTH + 1)]],
        'a postal code too short' => [['postal_code' => str_repeat('1', GuestAddressInput::MINIMUM_POSTAL_CODE_LENGTH - 1)]],
        'a postal code too long' => [['postal_code' => str_repeat('1', GuestAddressInput::MAXIMUM_POSTAL_CODE_LENGTH + 1)]],
        'no country' => [['country_code' => null]],
        'a three letter country' => [['country_code' => 'MEX']],
        'a one letter country' => [['country_code' => 'M']],
    ]);

    it('refuses a block with every key missing, rather than letting a php error through', function () {
        expect(fn () => GuestAddressInput::fromPayload(['unexpected' => true])?->validate())
            ->toThrow(InvalidCustomerAddress::class);
    });

    it('refuses as a domain failure the responder can classify', function () {
        try {
            GuestAddressInput::fromPayload(customerGuestAddressPayload(['street' => '']))?->validate();
            $thrown = null;
        } catch (InvalidCustomerAddress $refusal) {
            $thrown = $refusal;
        }

        expect($thrown)->toBeInstanceOf(DomainFailure::class)
            ->and($thrown?->errorCode())->toBe('invalid_customer_address')
            ->and($thrown?->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('names the street before anything else', function () {
        try {
            GuestAddressInput::fromPayload(['country_code' => 'MEX', 'postal_code' => '1'])?->validate();
            $thrown = null;
        } catch (InvalidCustomerAddress $refusal) {
            $thrown = $refusal;
        }

        expect($thrown?->getMessage())->toBe('A customer address needs a street.');
    });
});

describe('handing the address to the address book', function () {
    it('trims every field and upper-cases the country', function () {
        $snapshot = (new GuestAddressInput(
            street: '  Av. Reforma 123  ',
            city: '  Monterrey ',
            stateName: ' Nuevo León ',
            postalCode: ' 64000 ',
            countryCode: ' mx ',
        ))->toSnapshot();

        expect($snapshot)->toBeInstanceOf(CustomerAddressSnapshot::class)
            ->and($snapshot->street)->toBe('Av. Reforma 123')
            ->and($snapshot->city)->toBe('Monterrey')
            ->and($snapshot->stateName)->toBe('Nuevo León')
            ->and($snapshot->postalCode)->toBe('64000')
            ->and($snapshot->countryCode)->toBe('MX');
    });

    it('never names a catalogue state, because a visitor only ever types one', function () {
        expect(CustomerFixtures::guestAddress()->toSnapshot()->stateId)->toBeNull();
    });

    it('hands over a blank optional as nothing at all', function () {
        $snapshot = (new GuestAddressInput(
            street: CustomerFixtures::STREET,
            city: '   ',
            stateName: '',
            postalCode: null,
            countryCode: 'MX',
        ))->toSnapshot();

        expect($snapshot->city)->toBeNull()
            ->and($snapshot->stateName)->toBeNull()
            ->and($snapshot->postalCode)->toBeNull();
    });

    it('keeps the accents the visitor typed', function () {
        $snapshot = CustomerFixtures::guestAddress(street: 'Callejón del Ñandú 3', city: 'Querétaro')->toSnapshot();

        expect($snapshot->street)->toBe('Callejón del Ñandú 3')
            ->and($snapshot->city)->toBe('Querétaro')
            ->and($snapshot->stateName)->toBe(CustomerFixtures::STATE_NAME);
    });
});
