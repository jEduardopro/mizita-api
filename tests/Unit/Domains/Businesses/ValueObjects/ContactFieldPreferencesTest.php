<?php

declare(strict_types=1);

use App\Domains\Businesses\ValueObjects\ContactFieldPreference;
use App\Domains\Businesses\ValueObjects\ContactFieldPreferences;

function contactFieldPreferences(
    ContactFieldPreference $phone = ContactFieldPreference::Required,
    ContactFieldPreference $email = ContactFieldPreference::Optional,
    ContactFieldPreference $address = ContactFieldPreference::Hidden,
): ContactFieldPreferences {
    return new ContactFieldPreferences($phone, $email, $address);
}

describe('changesAnythingOf', function () {
    it('changes nothing when the same requirements are resubmitted', function () {
        expect(contactFieldPreferences()->changesAnythingOf(contactFieldPreferences()))->toBeFalse();
    });

    it('changes nothing when every field carries the same requirement on both sides', function (ContactFieldPreference $preference) {
        expect(contactFieldPreferences($preference, $preference, $preference)
            ->changesAnythingOf(contactFieldPreferences($preference, $preference, $preference)))
            ->toBeFalse();
    })->with(ContactFieldPreference::cases());

    it('changes the requirements when the phone differs', function (ContactFieldPreference $submitted) {
        expect(contactFieldPreferences(phone: $submitted)->changesAnythingOf(contactFieldPreferences()))->toBeTrue();
    })->with([
        'optional' => ContactFieldPreference::Optional,
        'hidden' => ContactFieldPreference::Hidden,
    ]);

    it('changes the requirements when the email differs', function (ContactFieldPreference $submitted) {
        expect(contactFieldPreferences(email: $submitted)->changesAnythingOf(contactFieldPreferences()))->toBeTrue();
    })->with([
        'required' => ContactFieldPreference::Required,
        'hidden' => ContactFieldPreference::Hidden,
    ]);

    it('changes the requirements when the address differs', function (ContactFieldPreference $submitted) {
        expect(contactFieldPreferences(address: $submitted)->changesAnythingOf(contactFieldPreferences()))->toBeTrue();
    })->with([
        'required' => ContactFieldPreference::Required,
        'optional' => ContactFieldPreference::Optional,
    ]);

    it('changes the requirements when two fields swap theirs', function () {
        expect(contactFieldPreferences(
            phone: ContactFieldPreference::Optional,
            email: ContactFieldPreference::Required,
        )->changesAnythingOf(contactFieldPreferences()))->toBeTrue();
    });
});
