<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Exceptions\InvalidPublicGuestAddress;
use App\Domains\PublicCatalog\Exceptions\MissingGuestContactField;
use App\Domains\PublicCatalog\ValueObjects\GuestFieldRequirement;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestAddress;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

describe('validating the booking a visitor sent', function () {
    it('accepts a booking whose guest is well formed', function () {
        expect(fn () => PublicCatalogFixtures::bookingRequest()->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a booking whose guest address is out of bounds', function () {
        $request = PublicCatalogFixtures::bookingRequest(guest: PublicCatalogFixtures::guestDetails(
            address: PublicCatalogFixtures::guestAddress(
                street: str_repeat('a', PublicGuestAddress::MAXIMUM_STREET_LENGTH + 1),
            ),
        ));

        expect(fn () => $request->validate())->toThrow(InvalidPublicGuestAddress::class);
    });
});

describe('keeping only what the business collects', function () {
    it('applies the form settings to the guest and nothing else', function () {
        $request = PublicCatalogFixtures::bookingRequest(
            guest: PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress()),
            notes: 'Prefiero por la mañana.',
        );

        $collected = $request->collectingOnly(PublicCatalogFixtures::contactFields(
            phone: GuestFieldRequirement::Hidden,
            email: GuestFieldRequirement::Optional,
            address: GuestFieldRequirement::Hidden,
        ));

        expect($collected->serviceId)->toBe(PublicCatalogFixtures::SERVICE_ID)
            ->and($collected->staffMemberId)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($collected->startsAt)->toBe(PublicCatalogFixtures::STARTS_AT)
            ->and($collected->notes)->toBe('Prefiero por la mañana.')
            ->and($collected->guest->name)->toBe(PublicCatalogFixtures::GUEST_NAME)
            ->and($collected->guest->email)->toBe(PublicCatalogFixtures::GUEST_EMAIL)
            ->and($collected->guest->phoneCountryCode)->toBeNull()
            ->and($collected->guest->phoneNationalNumber)->toBeNull()
            ->and($collected->guest->address)->toBeNull();
    });

    it('lets the refusal out when the visitor left a required field out', function () {
        $request = PublicCatalogFixtures::bookingRequest(guest: PublicCatalogFixtures::guestDetails(email: null));

        expect(fn () => $request->collectingOnly(PublicCatalogFixtures::contactFields(
            email: GuestFieldRequirement::Required,
        )))->toThrow(MissingGuestContactField::class);
    });

    it('leaves the request it was asked about untouched', function () {
        $request = PublicCatalogFixtures::bookingRequest();

        $request->collectingOnly(PublicCatalogFixtures::contactFields(phone: GuestFieldRequirement::Hidden));

        expect($request->guest->phoneNationalNumber)->toBe(PublicCatalogFixtures::GUEST_PHONE_NATIONAL_NUMBER);
    });
});
