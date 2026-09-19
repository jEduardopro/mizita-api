<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CustomerPhoneInput;
use App\Domains\Customers\Application\Dtos\GuestContactInput;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerNotes;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Domains\Customers\Exceptions\InvalidGuestContact;
use App\Shared\ValueObjects\DomainFailureKind;
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
            ->and($contact->notes)->toBe(CustomerFixtures::NOTES);
    });

    it('survives a payload with every key missing', function () {
        $contact = GuestContactInput::fromRequest([]);

        expect($contact->name)->toBe('')
            ->and($contact->email)->toBeNull()
            ->and($contact->phone)->toBeNull()
            ->and($contact->notes)->toBeNull();
    });

    it('turns a value of the wrong type into the empty one, rather than a php error', function () {
        $contact = GuestContactInput::fromRequest([
            'name' => ['Ada'],
            'email' => 7,
            'phone' => 'nonsense',
            'notes' => false,
        ]);

        expect($contact->name)->toBe('')
            ->and($contact->email)->toBeNull()
            ->and($contact->phone)->toBeNull()
            ->and($contact->notes)->toBeNull();
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

    it('refuses a contact with neither an email nor a phone, because nobody could be reached', function () {
        expect(fn () => CustomerFixtures::guestContact(email: null, phone: null)->validate())
            ->toThrow(InvalidGuestContact::class);
    });

    it('refuses the unreachable contact as a domain failure the responder can classify', function () {
        try {
            CustomerFixtures::guestContact(email: null, phone: null)->validate();
            $thrown = null;
        } catch (InvalidGuestContact $refusal) {
            $thrown = $refusal;
        }

        expect($thrown?->errorCode())->toBe('invalid_guest_contact')
            ->and($thrown?->kind())->toBe(DomainFailureKind::Invalid);
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

    it('checks the contact channel only once every field is readable', function () {
        expect(fn () => GuestContactInput::fromRequest(['name' => '', 'email' => null, 'phone' => null])->validate())
            ->toThrow(InvalidCustomerName::class);
    });
});
