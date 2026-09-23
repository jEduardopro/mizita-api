<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\GuestAddressInput;
use App\Domains\Appointments\Application\Dtos\GuestDetailsInput;
use App\Domains\Appointments\Exceptions\InvalidGuestAddress;
use App\Domains\Appointments\Exceptions\InvalidGuestEmail;
use App\Domains\Appointments\Exceptions\InvalidGuestName;
use App\Domains\Appointments\Exceptions\InvalidGuestPhone;
use Tests\Support\Appointments\AppointmentFixtures;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function guestDetailsPayload(array $overrides = []): array
{
    return [
        'name' => AppointmentFixtures::GUEST_NAME,
        'email' => AppointmentFixtures::GUEST_EMAIL,
        'phone' => ['country_code' => 'MX', 'national_number' => '5512345678'],
        'address' => [
            'street' => AppointmentFixtures::GUEST_STREET,
            'city' => AppointmentFixtures::GUEST_CITY,
            'state' => AppointmentFixtures::GUEST_STATE_NAME,
            'postal_code' => AppointmentFixtures::GUEST_POSTAL_CODE,
            'country_code' => AppointmentFixtures::GUEST_COUNTRY_CODE,
        ],
        ...$overrides,
    ];
}

describe('reading a payload', function () {
    it('assembles itself, address included, from the guest block a visitor sent', function () {
        $details = GuestDetailsInput::fromPayload(guestDetailsPayload());

        expect($details->name)->toBe(AppointmentFixtures::GUEST_NAME)
            ->and($details->email)->toBe(AppointmentFixtures::GUEST_EMAIL)
            ->and($details->phoneCountryCode)->toBe('MX')
            ->and($details->phoneNationalNumber)->toBe('5512345678')
            ->and($details->address)->toBeInstanceOf(GuestAddressInput::class)
            ->and($details->address?->street)->toBe(AppointmentFixtures::GUEST_STREET)
            ->and($details->address?->stateName)->toBe(AppointmentFixtures::GUEST_STATE_NAME);
    });

    it('survives a payload with every key missing', function (mixed $payload) {
        $details = GuestDetailsInput::fromPayload($payload);

        expect($details->name)->toBe('')
            ->and($details->email)->toBeNull()
            ->and($details->phoneCountryCode)->toBeNull()
            ->and($details->phoneNationalNumber)->toBeNull()
            ->and($details->address)->toBeNull();
    })->with([
        'an empty array' => [[]],
        'null' => null,
        'a string' => 'Ada',
    ]);

    it('reads a guest who typed no address', function (mixed $address) {
        expect(GuestDetailsInput::fromPayload(guestDetailsPayload(['address' => $address]))->address)->toBeNull();
    })->with([
        'null' => null,
        'an empty array' => [[]],
        'a string' => 'Av. Reforma 123',
    ]);
});

describe('validating', function () {
    it('accepts a guest who left nothing but a name', function () {
        expect(fn () => GuestDetailsInput::fromPayload(['name' => AppointmentFixtures::GUEST_NAME])->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts a guest carrying every field, address included', function () {
        expect(fn () => GuestDetailsInput::fromPayload(guestDetailsPayload())->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses a guest block the form request would have rejected', function (array $overrides, string $exception) {
        expect(fn () => GuestDetailsInput::fromPayload(guestDetailsPayload($overrides))->validate())
            ->toThrow($exception);
    })->with([
        'no name' => [['name' => ''], InvalidGuestName::class],
        'a malformed email' => [['email' => 'nope'], InvalidGuestEmail::class],
        'half a phone' => [['phone' => ['country_code' => 'MX']], InvalidGuestPhone::class],
        'an address with no street' => [
            ['address' => ['city' => AppointmentFixtures::GUEST_CITY, 'country_code' => 'MX']],
            InvalidGuestAddress::class,
        ],
        'an address with a malformed country' => [
            ['address' => ['street' => AppointmentFixtures::GUEST_STREET, 'country_code' => 'MEX']],
            InvalidGuestAddress::class,
        ],
    ]);

    it('checks the address only once the name, email and phone are readable', function () {
        expect(fn () => GuestDetailsInput::fromPayload(guestDetailsPayload([
            'email' => 'nope',
            'address' => ['street' => '', 'country_code' => ''],
        ]))->validate())->toThrow(InvalidGuestEmail::class);
    });
});

describe('turning into the contact the booking carries', function () {
    it('carries the typed address along with the name', function () {
        $contact = GuestDetailsInput::fromPayload(guestDetailsPayload(['email' => null, 'phone' => null]))->toContact();

        expect($contact->name)->toBe(AppointmentFixtures::GUEST_NAME)
            ->and($contact->email)->toBeNull()
            ->and($contact->phone)->toBeNull()
            ->and($contact->address?->street)->toBe(AppointmentFixtures::GUEST_STREET)
            ->and($contact->address?->countryCode)->toBe(AppointmentFixtures::GUEST_COUNTRY_CODE);
    });

    it('carries no address when the visitor typed none', function () {
        expect(GuestDetailsInput::fromPayload(['name' => AppointmentFixtures::GUEST_NAME])->toContact()->address)
            ->toBeNull();
    });
});
