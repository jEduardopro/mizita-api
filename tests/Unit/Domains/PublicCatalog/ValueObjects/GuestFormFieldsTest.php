<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Exceptions\MissingGuestContactField;
use App\Domains\PublicCatalog\ValueObjects\GuestFieldRequirement;
use App\Domains\PublicCatalog\ValueObjects\GuestFormFields;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestAddress;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestDetails;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

function publicGuestFormFields(
    GuestFieldRequirement $phone = GuestFieldRequirement::Optional,
    GuestFieldRequirement $email = GuestFieldRequirement::Optional,
    GuestFieldRequirement $address = GuestFieldRequirement::Optional,
): GuestFormFields {
    return PublicCatalogFixtures::contactFields(phone: $phone, email: $email, address: $address);
}

function publicGuestRefusalApplying(GuestFormFields $fields, PublicGuestDetails $guest): ?MissingGuestContactField
{
    try {
        $fields->applyTo($guest);
    } catch (MissingGuestContactField $refusal) {
        return $refusal;
    }

    return null;
}

function publicFullyFilledGuest(): PublicGuestDetails
{
    return PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress());
}

describe('a field the business hid', function () {
    it('drops the phone the visitor sent anyway, so it is never stored', function () {
        $guest = publicGuestFormFields(phone: GuestFieldRequirement::Hidden)->applyTo(publicFullyFilledGuest());

        expect($guest->phoneCountryCode)->toBeNull()
            ->and($guest->phoneNationalNumber)->toBeNull()
            ->and($guest->email)->toBe(PublicCatalogFixtures::GUEST_EMAIL)
            ->and($guest->address)->toEqual(PublicCatalogFixtures::guestAddress());
    });

    it('drops the email the visitor sent anyway, so it is never stored', function () {
        $guest = publicGuestFormFields(email: GuestFieldRequirement::Hidden)->applyTo(publicFullyFilledGuest());

        expect($guest->email)->toBeNull()
            ->and($guest->phoneCountryCode)->toBe(PublicCatalogFixtures::GUEST_PHONE_COUNTRY_CODE)
            ->and($guest->phoneNationalNumber)->toBe(PublicCatalogFixtures::GUEST_PHONE_NATIONAL_NUMBER)
            ->and($guest->address)->toEqual(PublicCatalogFixtures::guestAddress());
    });

    it('drops the address the visitor sent anyway, so it is never stored', function () {
        $guest = publicGuestFormFields(address: GuestFieldRequirement::Hidden)->applyTo(publicFullyFilledGuest());

        expect($guest->address)->toBeNull()
            ->and($guest->email)->toBe(PublicCatalogFixtures::GUEST_EMAIL)
            ->and($guest->phoneNationalNumber)->toBe(PublicCatalogFixtures::GUEST_PHONE_NATIONAL_NUMBER);
    });

    it('drops a half filled address without refusing it, because nobody asked for one', function () {
        $guest = publicGuestFormFields(address: GuestFieldRequirement::Hidden)->applyTo(PublicCatalogFixtures::guestDetails(
            address: PublicCatalogFixtures::guestAddress(city: null, postalCode: null),
        ));

        expect($guest->address)->toBeNull();
    });

    it('keeps the name whatever else it drops', function () {
        $guest = publicGuestFormFields(
            GuestFieldRequirement::Hidden,
            GuestFieldRequirement::Hidden,
            GuestFieldRequirement::Hidden,
        )->applyTo(publicFullyFilledGuest());

        expect($guest->name)->toBe(PublicCatalogFixtures::GUEST_NAME);
    });
});

describe('a business that asks for nothing but the name', function () {
    beforeEach(function () {
        $this->nameOnly = publicGuestFormFields(
            GuestFieldRequirement::Hidden,
            GuestFieldRequirement::Hidden,
            GuestFieldRequirement::Hidden,
        );
    });

    it('books a visitor who gave a name alone', function () {
        $guest = $this->nameOnly->applyTo(new PublicGuestDetails(PublicCatalogFixtures::GUEST_NAME, null, null, null));

        expect($guest->name)->toBe(PublicCatalogFixtures::GUEST_NAME)
            ->and($guest->email)->toBeNull()
            ->and($guest->phoneCountryCode)->toBeNull()
            ->and($guest->phoneNationalNumber)->toBeNull()
            ->and($guest->address)->toBeNull();
    });

    it('keeps nothing but the name of a visitor who filled in everything', function () {
        $guest = $this->nameOnly->applyTo(publicFullyFilledGuest());

        expect($guest)->toEqual(new PublicGuestDetails(PublicCatalogFixtures::GUEST_NAME, null, null, null));
    });
});

describe('a field the business required', function () {
    it('refuses a visitor who left it out, naming the field', function (GuestFormFields $fields, PublicGuestDetails $guest, string $code) {
        $refusal = publicGuestRefusalApplying($fields, $guest);

        expect($refusal)->toBeInstanceOf(MissingGuestContactField::class)
            ->and($refusal->errorCode())->toBe($code)
            ->and($refusal->kind())->toBe(DomainFailureKind::Invalid);
    })->with([
        'no phone' => [
            fn () => publicGuestFormFields(phone: GuestFieldRequirement::Required),
            fn () => PublicCatalogFixtures::guestDetails(phoneCountryCode: null, phoneNationalNumber: null),
            'missing_guest_phone',
        ],
        'a phone with no national number' => [
            fn () => publicGuestFormFields(phone: GuestFieldRequirement::Required),
            fn () => PublicCatalogFixtures::guestDetails(phoneNationalNumber: null),
            'missing_guest_phone',
        ],
        'a phone with no country code' => [
            fn () => publicGuestFormFields(phone: GuestFieldRequirement::Required),
            fn () => PublicCatalogFixtures::guestDetails(phoneCountryCode: null),
            'missing_guest_phone',
        ],
        'no email' => [
            fn () => publicGuestFormFields(email: GuestFieldRequirement::Required),
            fn () => PublicCatalogFixtures::guestDetails(email: null),
            'missing_guest_email',
        ],
        'no address at all' => [
            fn () => publicGuestFormFields(address: GuestFieldRequirement::Required),
            fn () => PublicCatalogFixtures::guestDetails(address: null),
            'missing_guest_address',
        ],
        'an address with every part blank' => [
            fn () => publicGuestFormFields(address: GuestFieldRequirement::Required),
            fn () => PublicCatalogFixtures::guestDetails(address: new PublicGuestAddress(null, null, null, null)),
            'missing_guest_address',
        ],
        'an address with no street' => [
            fn () => publicGuestFormFields(address: GuestFieldRequirement::Required),
            fn () => PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress(street: null)),
            'missing_guest_address',
        ],
        'an address with no city' => [
            fn () => publicGuestFormFields(address: GuestFieldRequirement::Required),
            fn () => PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress(city: null)),
            'missing_guest_address',
        ],
        'an address with no state' => [
            fn () => publicGuestFormFields(address: GuestFieldRequirement::Required),
            fn () => PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress(state: null)),
            'missing_guest_address',
        ],
        'an address with no postal code' => [
            fn () => publicGuestFormFields(address: GuestFieldRequirement::Required),
            fn () => PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress(postalCode: null)),
            'missing_guest_address',
        ],
    ]);

    it('keeps every required field the visitor filled in', function () {
        $guest = publicGuestFormFields(
            GuestFieldRequirement::Required,
            GuestFieldRequirement::Required,
            GuestFieldRequirement::Required,
        )->applyTo(publicFullyFilledGuest());

        expect($guest)->toEqual(publicFullyFilledGuest());
    });

    it('books under the defaults a business never changed with a phone and nothing else', function () {
        $guest = PublicCatalogFixtures::contactFields()->applyTo(
            PublicCatalogFixtures::guestDetails(email: null, address: PublicCatalogFixtures::guestAddress()),
        );

        expect($guest->phoneNationalNumber)->toBe(PublicCatalogFixtures::GUEST_PHONE_NATIONAL_NUMBER)
            ->and($guest->email)->toBeNull()
            ->and($guest->address)->toBeNull();
    });
});

describe('a field the business left optional', function () {
    it('accepts a visitor who left it out', function (GuestFormFields $fields, PublicGuestDetails $guest) {
        expect(publicGuestRefusalApplying($fields, $guest))->toBeNull();
    })->with([
        'no phone' => [
            fn () => publicGuestFormFields(),
            fn () => PublicCatalogFixtures::guestDetails(phoneCountryCode: null, phoneNationalNumber: null),
        ],
        'no email' => [
            fn () => publicGuestFormFields(),
            fn () => PublicCatalogFixtures::guestDetails(email: null),
        ],
        'no address at all' => [
            fn () => publicGuestFormFields(),
            fn () => PublicCatalogFixtures::guestDetails(address: null),
        ],
        'an address with every part blank' => [
            fn () => publicGuestFormFields(),
            fn () => PublicCatalogFixtures::guestDetails(address: new PublicGuestAddress(null, null, null, null)),
        ],
    ]);

    it('stores no address at all for one left entirely blank', function () {
        $guest = publicGuestFormFields()->applyTo(
            PublicCatalogFixtures::guestDetails(address: new PublicGuestAddress(null, null, null, null)),
        );

        expect($guest->address)->toBeNull();
    });

    it('keeps every optional field the visitor filled in', function () {
        expect(publicGuestFormFields()->applyTo(publicFullyFilledGuest()))->toEqual(publicFullyFilledGuest());
    });

    it('refuses an address filled in halfway, rather than store a place nobody can find', function (PublicGuestAddress $address) {
        $refusal = publicGuestRefusalApplying(publicGuestFormFields(), PublicCatalogFixtures::guestDetails(address: $address));

        expect($refusal?->errorCode())->toBe('missing_guest_address');
    })->with([
        'a street alone' => [fn () => new PublicGuestAddress(PublicCatalogFixtures::GUEST_STREET, null, null, null)],
        'a postal code alone' => [fn () => new PublicGuestAddress(null, null, null, PublicCatalogFixtures::GUEST_POSTAL_CODE)],
        'everything but the state' => [fn () => PublicCatalogFixtures::guestAddress(state: null)],
        'everything but the street' => [fn () => PublicCatalogFixtures::guestAddress(street: null)],
    ]);
});
