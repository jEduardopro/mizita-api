<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\ValueObjects\ContactFieldRequirement;
use App\Domains\PublicCatalog\Infrastructure\Gateways\BookingPoliciesGuestContactFields;
use App\Domains\PublicCatalog\ValueObjects\GuestFieldRequirement;
use App\Domains\PublicCatalog\ValueObjects\GuestFormFields;
use Tests\Support\BookingPolicies\BookingPolicyFixtures;
use Tests\Support\BookingPolicies\FakeBookingPolicyRepository;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->policies = new FakeBookingPolicyRepository;

    $this->gateway = new BookingPoliciesGuestContactFields($this->policies);

    $this->read = fn (string $businessId = PublicCatalogFixtures::BUSINESS_ID): GuestFormFields => $this->gateway
        ->forBusiness($businessId);

    $this->storeFor = function (string $businessId, ContactFieldRequirement ...$requirements): void {
        $this->policies->store(BookingPolicyFixtures::policy(
            businessId: $businessId,
            contactFields: BookingPolicyFixtures::contactFields(...$requirements),
        ));
    };
});

describe('a business that never saved a booking policy', function () {
    it('asks for a phone, offers the email and hides the address', function () {
        $fields = ($this->read)();

        expect($fields->phone)->toBe(GuestFieldRequirement::Required)
            ->and($fields->email)->toBe(GuestFieldRequirement::Optional)
            ->and($fields->address)->toBe(GuestFieldRequirement::Hidden);
    });

    it('writes no policy for it, because a read never creates one', function () {
        ($this->read)();

        expect($this->policies->saved)->toBe([])
            ->and($this->policies->deleted)->toBe([]);
    });
});

describe('a business that saved its form settings', function () {
    it('translates each requirement it chose for the phone', function (ContactFieldRequirement $stored, GuestFieldRequirement $expected) {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, phone: $stored);

        expect(($this->read)()->phone)->toBe($expected);
    })->with('guest contact field requirements');

    it('translates each requirement it chose for the email', function (ContactFieldRequirement $stored, GuestFieldRequirement $expected) {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, email: $stored);

        expect(($this->read)()->email)->toBe($expected);
    })->with('guest contact field requirements');

    it('translates each requirement it chose for the address', function (ContactFieldRequirement $stored, GuestFieldRequirement $expected) {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID, address: $stored);

        expect(($this->read)()->address)->toBe($expected);
    })->with('guest contact field requirements');

    it('keeps each field apart from the others', function () {
        ($this->storeFor)(
            PublicCatalogFixtures::BUSINESS_ID,
            ContactFieldRequirement::Hidden,
            ContactFieldRequirement::Required,
            ContactFieldRequirement::Optional,
        );

        expect(($this->read)())->toEqual(PublicCatalogFixtures::contactFields(
            phone: GuestFieldRequirement::Hidden,
            email: GuestFieldRequirement::Required,
            address: GuestFieldRequirement::Optional,
        ));
    });

    it('writes nothing back while reading', function () {
        ($this->storeFor)(PublicCatalogFixtures::BUSINESS_ID);

        ($this->read)();

        expect($this->policies->saved)->toBe([])
            ->and($this->policies->deleted)->toBe([]);
    });
});

describe('the business it reads for', function () {
    it('asks for the policy of the business uuid it was given', function () {
        ($this->read)(PublicCatalogFixtures::OTHER_BUSINESS_ID);

        expect($this->policies->businessIdsSeen)->toBe([PublicCatalogFixtures::OTHER_BUSINESS_ID]);
    });

    it('never reads the settings another business saved', function () {
        ($this->storeFor)(
            PublicCatalogFixtures::OTHER_BUSINESS_ID,
            ContactFieldRequirement::Hidden,
            ContactFieldRequirement::Required,
            ContactFieldRequirement::Required,
        );

        expect(($this->read)(PublicCatalogFixtures::BUSINESS_ID))->toEqual(PublicCatalogFixtures::contactFields());
    });
});

dataset('guest contact field requirements', [
    'hidden' => [ContactFieldRequirement::Hidden, GuestFieldRequirement::Hidden],
    'optional' => [ContactFieldRequirement::Optional, GuestFieldRequirement::Optional],
    'required' => [ContactFieldRequirement::Required, GuestFieldRequirement::Required],
]);
