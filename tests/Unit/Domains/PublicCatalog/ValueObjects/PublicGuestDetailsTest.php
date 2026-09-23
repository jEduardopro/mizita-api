<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Exceptions\InvalidPublicGuestAddress;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestAddress;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestDetails;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

describe('validating the details a visitor typed', function () {
    it('accepts a visitor who sent no address', function () {
        expect(fn () => PublicCatalogFixtures::guestDetails()->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a visitor with a well formed address', function () {
        expect(fn () => PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress())->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses a visitor whose address is out of bounds', function () {
        $guest = PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress(
            city: str_repeat('a', PublicGuestAddress::MAXIMUM_CITY_LENGTH + 1),
        ));

        expect(fn () => $guest->validate())->toThrow(InvalidPublicGuestAddress::class);
    });
});

describe('what the visitor gave', function () {
    it('has a phone only with both a country code and a national number', function (?string $countryCode, ?string $nationalNumber, bool $hasPhone) {
        expect(PublicCatalogFixtures::guestDetails(
            phoneCountryCode: $countryCode,
            phoneNationalNumber: $nationalNumber,
        )->hasPhone())->toBe($hasPhone);
    })->with([
        'both halves' => ['MX', '5512345678', true],
        'no national number' => ['MX', null, false],
        'no country code' => [null, '5512345678', false],
        'neither' => [null, null, false],
    ]);

    it('has an email only when one was given', function (?string $email, bool $hasEmail) {
        expect(PublicCatalogFixtures::guestDetails(email: $email)->hasEmail())->toBe($hasEmail);
    })->with([
        'an email' => [PublicCatalogFixtures::GUEST_EMAIL, true],
        'none' => [null, false],
    ]);

    it('counts a missing address as blank and never as complete', function () {
        $guest = PublicCatalogFixtures::guestDetails(address: null);

        expect($guest->hasBlankAddress())->toBeTrue()
            ->and($guest->hasCompleteAddress())->toBeFalse();
    });

    it('reads completeness and blankness off the address it carries', function (PublicGuestAddress $address, bool $complete, bool $blank) {
        $guest = PublicCatalogFixtures::guestDetails(address: $address);

        expect($guest->hasCompleteAddress())->toBe($complete)
            ->and($guest->hasBlankAddress())->toBe($blank);
    })->with([
        'complete' => [fn () => PublicCatalogFixtures::guestAddress(), true, false],
        'partial' => [fn () => PublicCatalogFixtures::guestAddress(city: null), false, false],
        'blank' => [fn () => new PublicGuestAddress(null, null, null, null), false, true],
    ]);
});

describe('dropping a field the business does not collect', function () {
    beforeEach(function () {
        $this->guest = PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress());
    });

    it('drops both halves of the phone and keeps the rest', function () {
        expect($this->guest->withoutPhone())->toEqual(new PublicGuestDetails(
            name: PublicCatalogFixtures::GUEST_NAME,
            email: PublicCatalogFixtures::GUEST_EMAIL,
            phoneCountryCode: null,
            phoneNationalNumber: null,
            address: PublicCatalogFixtures::guestAddress(),
        ));
    });

    it('drops the email and keeps the rest', function () {
        expect($this->guest->withoutEmail())->toEqual(new PublicGuestDetails(
            name: PublicCatalogFixtures::GUEST_NAME,
            email: null,
            phoneCountryCode: PublicCatalogFixtures::GUEST_PHONE_COUNTRY_CODE,
            phoneNationalNumber: PublicCatalogFixtures::GUEST_PHONE_NATIONAL_NUMBER,
            address: PublicCatalogFixtures::guestAddress(),
        ));
    });

    it('drops the address and keeps the rest', function () {
        expect($this->guest->withoutAddress())->toEqual(new PublicGuestDetails(
            name: PublicCatalogFixtures::GUEST_NAME,
            email: PublicCatalogFixtures::GUEST_EMAIL,
            phoneCountryCode: PublicCatalogFixtures::GUEST_PHONE_COUNTRY_CODE,
            phoneNationalNumber: PublicCatalogFixtures::GUEST_PHONE_NATIONAL_NUMBER,
            address: null,
        ));
    });

    it('leaves the details it was asked about untouched', function () {
        $this->guest->withoutPhone();
        $this->guest->withoutEmail();
        $this->guest->withoutAddress();

        expect($this->guest)->toEqual(PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress()));
    });
});
