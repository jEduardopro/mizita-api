<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\ContactInput;
use App\Domains\Businesses\Application\Dtos\PhoneNumberInput;
use App\Domains\Businesses\Exceptions\InvalidBusinessContactEmail;
use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;
use App\Domains\Businesses\ValueObjects\ContactEmail;
use App\Shared\ValueObjects\CountryCode;
use Tests\Support\Businesses\SettingsFixtures;
use Tests\Support\PhoneNumbers;

it('accepts the contact details as the form paints them', function () {
    expect(fn () => SettingsFixtures::contact()->validate())->not->toThrow(Throwable::class);
});

it('holds what it was handed without normalising it, because the value object does that on the way out', function () {
    $contact = SettingsFixtures::contact(contactEmail: '  Hola@BARBERIA.COM  ');

    expect($contact->contactEmail)->toBe('  Hola@BARBERIA.COM  ')
        ->and(ContactEmail::fromString($contact->contactEmail)->value)->toBe('Hola@barberia.com');
});

describe('the contact email', function () {
    it('accepts a business that publishes no address', function () {
        expect(fn () => (new ContactInput(null, null))->validate())->not->toThrow(Throwable::class);
    });

    it('accepts an address a customer could write to', function (string $email) {
        expect(fn () => SettingsFixtures::contact(contactEmail: $email, phone: SettingsFixtures::submittedPhone())->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'plain' => 'hola@barberia.com',
        'a subdomain' => 'hola@citas.barberia.com',
        'a plus tag' => 'hola+citas@barberia.com',
        'padded' => '  hola@barberia.com  ',
        'uppercase' => 'HOLA@BARBERIA.COM',
    ]);

    it('refuses an address no customer could write to', function (string $email) {
        expect(fn () => SettingsFixtures::contact(contactEmail: $email)->validate())
            ->toThrow(InvalidBusinessContactEmail::class);
    })->with([
        'an empty string, which the request turns into null but a job could still hand over' => '',
        'whitespace only' => '   ',
        'no domain' => 'hola@',
        'no local part' => '@barberia.com',
        'no separator' => 'hola.barberia.com',
        'two separators' => 'hola@@barberia.com',
        'a space inside' => 'hola barberia@example.com',
        'one past the maximum length' => str_repeat('a', ContactEmail::MAXIMUM_LENGTH).'@barberia.com',
    ]);
});

describe('the phone number', function () {
    it('accepts a business that publishes no number', function () {
        expect(fn () => (new ContactInput(SettingsFixtures::CONTACT_EMAIL, null))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('hands the number to the input that owns its rules', function (string $countryCode, string $nationalNumber) {
        expect(fn () => SettingsFixtures::contact(
            phone: new PhoneNumberInput($countryCode, $nationalNumber),
        )->validate())->toThrow(UnsupportedPhoneNumber::class);
    })->with([
        'a country code of one letter' => ['M', '5512345678'],
        'a country code of three letters' => ['MEX', '5512345678'],
        'no country code' => ['', '5512345678'],
        'a blank number' => ['MX', '   '],
        'a number longer than any country dials' => ['MX', str_repeat('5', 25)],
    ]);

    it('accepts a number the parser will later be asked to make sense of', function (CountryCode $country, string $nationalNumber) {
        expect(fn () => SettingsFixtures::contact(
            phone: SettingsFixtures::submittedPhone($country, $nationalNumber),
        )->validate())->not->toThrow(Throwable::class);
    })->with([
        'Mexico' => [CountryCode::Mx, PhoneNumbers::MX_NATIONAL_NUMBER],
        'the United States' => [CountryCode::Us, PhoneNumbers::US_NATIONAL_NUMBER],
    ]);
});

it('names the email before the phone, in the order a reader fills the form', function () {
    expect(fn () => SettingsFixtures::contact(
        contactEmail: 'hola.barberia.com',
        phone: new PhoneNumberInput('', ''),
    )->validate())->toThrow(InvalidBusinessContactEmail::class);
});
