<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Exceptions\InvalidPublicGuestAddress;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestAddress;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

describe('validating the address a visitor typed', function () {
    it('accepts a full address', function () {
        expect(fn () => PublicCatalogFixtures::guestAddress()->validate())->not->toThrow(Throwable::class);
    });

    it('accepts an address with every part left out, because presence is the form settings to decide', function () {
        expect(fn () => (new PublicGuestAddress(null, null, null, null))->validate())->not->toThrow(Throwable::class);
    });

    it('accepts every part at its maximum length, counted in characters rather than bytes', function () {
        $address = new PublicGuestAddress(
            street: str_repeat('ñ', PublicGuestAddress::MAXIMUM_STREET_LENGTH),
            city: str_repeat('é', PublicGuestAddress::MAXIMUM_CITY_LENGTH),
            state: str_repeat('ó', PublicGuestAddress::MAXIMUM_STATE_LENGTH),
            postalCode: str_repeat('9', PublicGuestAddress::MAXIMUM_POSTAL_CODE_LENGTH),
        );

        expect(fn () => $address->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a postal code at its minimum length', function () {
        $address = PublicCatalogFixtures::guestAddress(
            postalCode: str_repeat('1', PublicGuestAddress::MINIMUM_POSTAL_CODE_LENGTH),
        );

        expect(fn () => $address->validate())->not->toThrow(Throwable::class);
    });

    it('ignores the whitespace around a part when measuring it', function () {
        $address = PublicCatalogFixtures::guestAddress(
            street: '  '.str_repeat('a', PublicGuestAddress::MAXIMUM_STREET_LENGTH).'  ',
            postalCode: '  64000  ',
        );

        expect(fn () => $address->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a part past its bounds', function (PublicGuestAddress $address) {
        expect(fn () => $address->validate())->toThrow(InvalidPublicGuestAddress::class);
    })->with([
        'street too long' => [fn () => PublicCatalogFixtures::guestAddress(
            street: str_repeat('ñ', PublicGuestAddress::MAXIMUM_STREET_LENGTH + 1),
        )],
        'city too long' => [fn () => PublicCatalogFixtures::guestAddress(
            city: str_repeat('é', PublicGuestAddress::MAXIMUM_CITY_LENGTH + 1),
        )],
        'state too long' => [fn () => PublicCatalogFixtures::guestAddress(
            state: str_repeat('ó', PublicGuestAddress::MAXIMUM_STATE_LENGTH + 1),
        )],
        'postal code too short' => [fn () => PublicCatalogFixtures::guestAddress(
            postalCode: str_repeat('1', PublicGuestAddress::MINIMUM_POSTAL_CODE_LENGTH - 1),
        )],
        'postal code too long' => [fn () => PublicCatalogFixtures::guestAddress(
            postalCode: str_repeat('1', PublicGuestAddress::MAXIMUM_POSTAL_CODE_LENGTH + 1),
        )],
        'postal code of spaces alone' => [fn () => PublicCatalogFixtures::guestAddress(postalCode: '      ')],
    ]);

    it('refuses with a failure the caller can classify', function () {
        $address = PublicCatalogFixtures::guestAddress(
            street: str_repeat('a', PublicGuestAddress::MAXIMUM_STREET_LENGTH + 1),
        );

        try {
            $address->validate();
            $refusal = null;
        } catch (InvalidPublicGuestAddress $thrown) {
            $refusal = $thrown;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('invalid_guest_address');
    });
});

describe('how much of the address a visitor filled in', function () {
    it('is complete only with a street, a city, a state and a postal code', function (PublicGuestAddress $address, bool $complete) {
        expect($address->isComplete())->toBe($complete);
    })->with([
        'everything' => [fn () => PublicCatalogFixtures::guestAddress(), true],
        'no street' => [fn () => PublicCatalogFixtures::guestAddress(street: null), false],
        'no city' => [fn () => PublicCatalogFixtures::guestAddress(city: null), false],
        'no state' => [fn () => PublicCatalogFixtures::guestAddress(state: null), false],
        'no postal code' => [fn () => PublicCatalogFixtures::guestAddress(postalCode: null), false],
        'nothing' => [fn () => new PublicGuestAddress(null, null, null, null), false],
    ]);

    it('is blank only when every part was left out', function (PublicGuestAddress $address, bool $blank) {
        expect($address->isBlank())->toBe($blank);
    })->with([
        'nothing' => [fn () => new PublicGuestAddress(null, null, null, null), true],
        'a street alone' => [fn () => new PublicGuestAddress(PublicCatalogFixtures::GUEST_STREET, null, null, null), false],
        'a city alone' => [fn () => new PublicGuestAddress(null, PublicCatalogFixtures::GUEST_CITY, null, null), false],
        'a state alone' => [fn () => new PublicGuestAddress(null, null, PublicCatalogFixtures::GUEST_STATE, null), false],
        'a postal code alone' => [fn () => new PublicGuestAddress(null, null, null, PublicCatalogFixtures::GUEST_POSTAL_CODE), false],
        'everything' => [fn () => PublicCatalogFixtures::guestAddress(), false],
    ]);
});
