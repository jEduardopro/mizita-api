<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\BookPublicAppointmentInput;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\Exceptions\InvalidPublicGuestAddress;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestAddress;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

/**
 * @param  array<string, mixed>  $guest
 * @return array<string, mixed>
 */
function publicBookingPayload(array $guest = []): array
{
    return [
        'service_id' => PublicCatalogFixtures::SERVICE_ID,
        'staff_member_id' => PublicCatalogFixtures::TEAM_MEMBER_ID,
        'starts_at' => PublicCatalogFixtures::STARTS_AT,
        'notes' => 'Prefiero por la mañana.',
        'guest' => [
            'name' => PublicCatalogFixtures::GUEST_NAME,
            'email' => PublicCatalogFixtures::GUEST_EMAIL,
            'phone' => [
                'country_code' => PublicCatalogFixtures::GUEST_PHONE_COUNTRY_CODE,
                'national_number' => PublicCatalogFixtures::GUEST_PHONE_NATIONAL_NUMBER,
            ],
            'address' => [
                'street' => PublicCatalogFixtures::GUEST_STREET,
                'city' => PublicCatalogFixtures::GUEST_CITY,
                'state' => PublicCatalogFixtures::GUEST_STATE,
                'postal_code' => PublicCatalogFixtures::GUEST_POSTAL_CODE,
            ],
            ...$guest,
        ],
    ];
}

describe('building the booking from the payload', function () {
    it('reads every field the visitor sent, the address included', function () {
        $input = BookPublicAppointmentInput::fromRequest(publicBookingPayload(), PublicCatalogFixtures::SLUG);

        expect($input->slug)->toBe(PublicCatalogFixtures::SLUG)
            ->and($input->booking->serviceId)->toBe(PublicCatalogFixtures::SERVICE_ID)
            ->and($input->booking->staffMemberId)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($input->booking->startsAt)->toBe(PublicCatalogFixtures::STARTS_AT)
            ->and($input->booking->notes)->toBe('Prefiero por la mañana.')
            ->and($input->booking->guest->name)->toBe(PublicCatalogFixtures::GUEST_NAME)
            ->and($input->booking->guest->email)->toBe(PublicCatalogFixtures::GUEST_EMAIL)
            ->and($input->booking->guest->phoneCountryCode)->toBe(PublicCatalogFixtures::GUEST_PHONE_COUNTRY_CODE)
            ->and($input->booking->guest->phoneNationalNumber)->toBe(PublicCatalogFixtures::GUEST_PHONE_NATIONAL_NUMBER)
            ->and($input->booking->guest->address)->toEqual(new PublicGuestAddress(
                street: PublicCatalogFixtures::GUEST_STREET,
                city: PublicCatalogFixtures::GUEST_CITY,
                state: PublicCatalogFixtures::GUEST_STATE,
                postalCode: PublicCatalogFixtures::GUEST_POSTAL_CODE,
            ));
    });

    it('keeps the accents of an address untouched', function () {
        $input = BookPublicAppointmentInput::fromRequest(publicBookingPayload([
            'address' => ['street' => 'Calle Ñandú 7', 'city' => 'Mérida', 'state' => 'Yucatán', 'postal_code' => '97000'],
        ]), PublicCatalogFixtures::SLUG);

        expect($input->booking->guest->address)
            ->toEqual(new PublicGuestAddress('Calle Ñandú 7', 'Mérida', 'Yucatán', '97000'));
    });

    it('reads no address for a visitor who sent none', function (array $guest) {
        $payload = publicBookingPayload();
        $payload['guest'] = [...array_diff_key($payload['guest'], ['address' => true]), ...$guest];

        expect(BookPublicAppointmentInput::fromRequest($payload, PublicCatalogFixtures::SLUG)->booking->guest->address)
            ->toBeNull();
    })->with([
        'key absent' => [[]],
        'null' => [['address' => null]],
        'a string' => [['address' => 'Av. Reforma 123']],
        'a number' => [['address' => 42]],
    ]);

    it('reads every part left blank as absent', function (mixed $blank) {
        $input = BookPublicAppointmentInput::fromRequest(publicBookingPayload([
            'address' => ['street' => $blank, 'city' => $blank, 'state' => $blank, 'postal_code' => $blank],
        ]), PublicCatalogFixtures::SLUG);

        expect($input->booking->guest->address)->toEqual(new PublicGuestAddress(null, null, null, null))
            ->and($input->booking->guest->address->isBlank())->toBeTrue();
    })->with([
        'empty string' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'null' => null,
        'a number' => 64000,
        'an array' => [['64000']],
    ]);

    it('reads an empty address object as a blank address', function () {
        $input = BookPublicAppointmentInput::fromRequest(
            publicBookingPayload(['address' => []]),
            PublicCatalogFixtures::SLUG,
        );

        expect($input->booking->guest->address?->isBlank())->toBeTrue();
    });

    it('reads a partial address part by part', function () {
        $input = BookPublicAppointmentInput::fromRequest(
            publicBookingPayload(['address' => ['street' => PublicCatalogFixtures::GUEST_STREET]]),
            PublicCatalogFixtures::SLUG,
        );

        expect($input->booking->guest->address)
            ->toEqual(new PublicGuestAddress(PublicCatalogFixtures::GUEST_STREET, null, null, null));
    });

    it('never takes the address country from the visitor', function () {
        $input = BookPublicAppointmentInput::fromRequest(publicBookingPayload([
            'address' => [
                'street' => PublicCatalogFixtures::GUEST_STREET,
                'city' => PublicCatalogFixtures::GUEST_CITY,
                'state' => PublicCatalogFixtures::GUEST_STATE,
                'postal_code' => PublicCatalogFixtures::GUEST_POSTAL_CODE,
                'country_code' => 'US',
            ],
        ]), PublicCatalogFixtures::SLUG);

        expect(array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicGuestAddress::class))->getProperties(),
        ))->toBe(['street', 'city', 'state', 'postalCode'])
            ->and(json_encode($input->booking->guest->address, JSON_THROW_ON_ERROR))->not->toContain('US');
    });

    it('survives a payload with every key missing, leaving the refusal to a domain failure', function () {
        $input = BookPublicAppointmentInput::fromRequest([], PublicCatalogFixtures::SLUG);

        expect($input->booking->serviceId)->toBe('')
            ->and($input->booking->staffMemberId)->toBe('')
            ->and($input->booking->startsAt)->toBe('')
            ->and($input->booking->notes)->toBeNull()
            ->and($input->booking->guest->name)->toBe('')
            ->and($input->booking->guest->email)->toBeNull()
            ->and($input->booking->guest->phoneCountryCode)->toBeNull()
            ->and($input->booking->guest->phoneNationalNumber)->toBeNull()
            ->and($input->booking->guest->address)->toBeNull();
    });

    it('survives a guest that is not an object at all', function (mixed $guest) {
        $input = BookPublicAppointmentInput::fromRequest([...publicBookingPayload(), 'guest' => $guest], PublicCatalogFixtures::SLUG);

        expect($input->booking->guest->name)->toBe('')
            ->and($input->booking->guest->address)->toBeNull();
    })->with([
        'null' => null,
        'a string' => 'Ada',
        'a number' => 7,
    ]);
});

describe('validating the booking', function () {
    it('accepts a well formed payload', function () {
        $input = BookPublicAppointmentInput::fromRequest(publicBookingPayload(), PublicCatalogFixtures::SLUG);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a payload with no address, because whether one is owed is the business to decide', function () {
        $payload = publicBookingPayload();
        unset($payload['guest']['address']);

        expect(fn () => BookPublicAppointmentInput::fromRequest($payload, PublicCatalogFixtures::SLUG)->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts a partial address, because completeness is the business to decide', function () {
        $input = BookPublicAppointmentInput::fromRequest(
            publicBookingPayload(['address' => ['city' => PublicCatalogFixtures::GUEST_CITY]]),
            PublicCatalogFixtures::SLUG,
        );

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('refuses an address the form request would have refused', function (array $address) {
        $input = BookPublicAppointmentInput::fromRequest(
            publicBookingPayload(['address' => $address]),
            PublicCatalogFixtures::SLUG,
        );

        expect(fn () => $input->validate())->toThrow(InvalidPublicGuestAddress::class);
    })->with([
        'street too long' => [['street' => str_repeat('a', PublicGuestAddress::MAXIMUM_STREET_LENGTH + 1)]],
        'city too long' => [['city' => str_repeat('é', PublicGuestAddress::MAXIMUM_CITY_LENGTH + 1)]],
        'state too long' => [['state' => str_repeat('ó', PublicGuestAddress::MAXIMUM_STATE_LENGTH + 1)]],
        'postal code too short' => [['postal_code' => '123']],
        'postal code too long' => [['postal_code' => '12345678901']],
    ]);

    it('refuses with a failure the caller can classify', function () {
        $input = BookPublicAppointmentInput::fromRequest(
            publicBookingPayload(['address' => ['postal_code' => '123']]),
            PublicCatalogFixtures::SLUG,
        );

        try {
            $input->validate();
            $refusal = null;
        } catch (DomainFailure $thrown) {
            $refusal = $thrown;
        }

        expect($refusal)->toBeInstanceOf(InvalidPublicGuestAddress::class)
            ->and($refusal->errorCode())->toBe('invalid_guest_address');
    });

    it('refuses a malformed slug as a page that does not exist', function (string $slug) {
        $input = BookPublicAppointmentInput::fromRequest(publicBookingPayload(), $slug);

        expect(fn () => $input->validate())->toThrow(BusinessPageNotFound::class);
    })->with([
        'empty' => '',
        'uppercase' => 'Ada-Salon',
        'a path traversal' => '../etc',
    ]);
});
