<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\ValueObjects\ContactFieldRequirement;
use App\Domains\BookingPolicies\ValueObjects\ContactFields;

describe('the contact fields a business starts with', function () {
    beforeEach(function () {
        $this->defaults = ContactFields::defaults();
    });

    it('requires a phone, so the business can always reach the customer', function () {
        expect($this->defaults->phone)->toBe(ContactFieldRequirement::Required);
    });

    it('offers the email without insisting on it', function () {
        expect($this->defaults->email)->toBe(ContactFieldRequirement::Optional);
    });

    it('asks for no address at all', function () {
        expect($this->defaults->address)->toBe(ContactFieldRequirement::Hidden);
    });

    it('matches the defaults the class declares, which the migration and the factory also read', function () {
        expect($this->defaults->phone)->toBe(ContactFields::DEFAULT_PHONE)
            ->and($this->defaults->email)->toBe(ContactFields::DEFAULT_EMAIL)
            ->and($this->defaults->address)->toBe(ContactFields::DEFAULT_ADDRESS);
    });
});
