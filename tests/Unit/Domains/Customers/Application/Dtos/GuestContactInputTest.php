<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CustomerPhoneInput;
use App\Domains\Customers\Application\Dtos\GuestAddressInput;
use App\Domains\Customers\Application\Dtos\GuestContactInput;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\InvalidCustomerAddress;
use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerNotes;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use Tests\Support\Customers\CustomerFixtures;
use Tests\Support\PhoneNumbers;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function guestPayload(array $overrides = []): array
{
    return [
        'name' => CustomerFixtures::NAME,
        'email' => CustomerFixtures::EMAIL,
        'phone' => [
            'country_code' => CustomerFixtures::COUNTRY_CODE,
            'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER,
        ],
        'notes' => CustomerFixtures::NOTES,
        ...$overrides,
    ];
}

describe('reading a payload', function () {
    it('assembles itself from a body an anonymous visitor sent', function () {
        $contact = GuestContactInput::fromRequest(guestPayload());

        expect($contact->name)->toBe(CustomerFixtures::NAME)
            ->and($contact->email)->toBe(CustomerFixtures::EMAIL)
            ->and($contact->phone)->toBeInstanceOf(CustomerPhoneInput::class)
            ->and($contact->phone?->countryCode)->toBe(CustomerFixtures::COUNTRY_CODE)
            ->and($contact->phone?->nationalNumber)->toBe(PhoneNumbers::MX_NATIONAL_NUMBER)
            ->and($contact->notes)->toBe(CustomerFixtures::NOTES)
            ->and($contact->address)->toBeNull();
    });

    it('assembles the address the visitor typed as its own child', function () {
        $contact = GuestContactInput::fromRequest(guestPayload(['address' => [
            'street' => CustomerFixtures::STREET,
            'city' => CustomerFixtures::CITY,
            'state' => CustomerFixtures::STATE_NAME,
            'postal_code' => CustomerFixtures::POSTAL_CODE,
            'country_code' => CustomerFixtures::COUNTRY_CODE,
        ]]));

        expect($contact->address)->toBeInstanceOf(GuestAddressInput::class)
            ->and($contact->address?->street)->toBe(CustomerFixtures::STREET)
            ->and($contact->address?->city)->toBe(CustomerFixtures::CITY)
            ->and($contact->address?->stateName)->toBe(CustomerFixtures::STATE_NAME)
            ->and($contact->address?->postalCode)->toBe(CustomerFixtures::POSTAL_CODE)
            ->and($contact->address?->countryCode)->toBe(CustomerFixtures::COUNTRY_CODE);
    });

    it('survives a payload with every key missing', function () {
        $contact = GuestContactInput::fromRequest([]);

        expect($contact->name)->toBe('')
            ->and($contact->email)->toBeNull()
            ->and($contact->phone)->toBeNull()
            ->and($contact->notes)->toBeNull()
            ->and($contact->address)->toBeNull();
    });

    it('turns a value of the wrong type into the empty one, rather than a php error', function () {
        $contact = GuestContactInput::fromRequest([
            'name' => ['Ada'],
            'email' => 7,
            'phone' => 'nonsense',
            'notes' => false,
            'address' => 'Somewhere',
        ]);

        expect($contact->name)->toBe('')
            ->and($contact->email)->toBeNull()
            ->and($contact->phone)->toBeNull()
            ->and($contact->notes)->toBeNull()
            ->and($contact->address)->toBeNull();
    });

    it('reads a blank optional as nothing at all', function (mixed $blank) {
        $contact = GuestContactInput::fromRequest(guestPayload(['email' => $blank, 'notes' => $blank]));

        expect($contact->email)->toBeNull()
            ->and($contact->notes)->toBeNull();
    })->with([
        'an empty string' => '',
        'spaces' => '   ',
        'null' => null,
    ]);

    it('reads a guest who published no phone', function (mixed $phone) {
        expect(GuestContactInput::fromRequest(guestPayload(['phone' => $phone]))->phone)->toBeNull();
    })->with([
        'no key at all' => null,
        'an empty array' => [[]],
        'a string' => 'nonsense',
    ]);

    it('reads a guest who typed no address', function (mixed $address) {
        expect(GuestContactInput::fromRequest(guestPayload(['address' => $address]))->address)->toBeNull();
    })->with([
        'no key at all' => null,
        'an empty array' => [[]],
        'a string' => 'nonsense',
    ]);
});

describe('validating', function () {
    it('accepts a contact carrying both an email and a phone', function () {
        expect(fn () => GuestContactInput::fromRequest(guestPayload())->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a contact carrying an email alone', function () {
        expect(fn () => CustomerFixtures::guestContact(phone: null)->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a contact carrying a phone alone', function () {
        expect(fn () => CustomerFixtures::guestContact(email: null)->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a contact carrying nothing but a name', function () {
        expect(fn () => CustomerFixtures::guestContact(email: null, phone: null)->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts a name-only payload read straight off the wire', function () {
        expect(fn () => GuestContactInput::fromRequest(['name' => CustomerFixtures::NAME])->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts a contact carrying a well formed address', function () {
        expect(fn () => CustomerFixtures::guestContact(address: CustomerFixtures::guestAddress())->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses a payload the form request would have rejected', function (array $payload, string $exception) {
        expect(fn () => GuestContactInput::fromRequest(guestPayload($payload))->validate())->toThrow($exception);
    })->with([
        'no name' => [['name' => ''], InvalidCustomerName::class],
        'a blank name' => [['name' => '   '], InvalidCustomerName::class],
        'a name past the column' => [
            ['name' => str_repeat('a', Customer::MAXIMUM_NAME_LENGTH + 1)],
            InvalidCustomerName::class,
        ],
        'a malformed email' => [['email' => 'nope'], InvalidCustomerEmail::class],
        'a country that is not a code' => [
            ['phone' => ['country_code' => 'MEX', 'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER]],
            InvalidCustomerPhone::class,
        ],
        'a phone with no number' => [
            ['phone' => ['country_code' => 'MX', 'national_number' => '   ']],
            InvalidCustomerPhone::class,
        ],
        'notes past the column' => [
            ['notes' => str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH + 1)],
            InvalidCustomerNotes::class,
        ],
        'an address with no street' => [
            ['address' => ['city' => CustomerFixtures::CITY, 'country_code' => 'MX']],
            InvalidCustomerAddress::class,
        ],
        'an address with a malformed country' => [
            ['address' => ['street' => CustomerFixtures::STREET, 'country_code' => 'MEX']],
            InvalidCustomerAddress::class,
        ],
    ]);

    it('names the name first, then the email, then the phone, then the notes', function () {
        expect(fn () => GuestContactInput::fromRequest(guestPayload([
            'name' => '',
            'email' => 'nope',
            'phone' => ['country_code' => 'MEX', 'national_number' => ''],
            'notes' => str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH + 1),
        ]))->validate())->toThrow(InvalidCustomerName::class);

        expect(fn () => GuestContactInput::fromRequest(guestPayload([
            'email' => 'nope',
            'phone' => ['country_code' => 'MEX', 'national_number' => ''],
        ]))->validate())->toThrow(InvalidCustomerEmail::class);
    });

    it('checks the address only once every other field is readable', function () {
        expect(fn () => GuestContactInput::fromRequest([
            'name' => '',
            'address' => ['street' => '', 'country_code' => ''],
        ])->validate())->toThrow(InvalidCustomerName::class);
    });
});
