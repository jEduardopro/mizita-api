<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\ContactFieldsInput;
use App\Domains\Businesses\Exceptions\IncompleteContactFields;
use App\Domains\Businesses\Exceptions\InvalidContactFieldRequirement;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\SettingsFixtures;

function contactFieldsRefusal(ContactFieldsInput $input): ?Throwable
{
    try {
        $input->validate();
    } catch (Throwable $refusal) {
        return $refusal;
    }

    return null;
}

describe('reading a submitted section', function () {
    it('reads each requirement under the key the client sends', function () {
        $input = ContactFieldsInput::fromPayload(SettingsFixtures::contactFieldsSection());

        expect($input)->toBeInstanceOf(ContactFieldsInput::class)
            ->and($input?->phone)->toBe(SettingsFixtures::PHONE_FIELD)
            ->and($input?->email)->toBe(SettingsFixtures::EMAIL_FIELD)
            ->and($input?->address)->toBe(SettingsFixtures::ADDRESS_FIELD);
    });

    it('treats a section it cannot read as one the client never sent', function (mixed $payload) {
        expect(ContactFieldsInput::fromPayload($payload))->toBeNull();
    })->with([
        'null' => null,
        'a string' => 'required',
        'a number' => 7,
        'a boolean' => true,
    ]);

    it('survives a section with every key missing, rather than raising a php error', function () {
        $input = ContactFieldsInput::fromPayload([]);

        expect($input?->phone)->toBe('')
            ->and($input?->email)->toBe('')
            ->and($input?->address)->toBe('');
    });

    it('turns a value of the wrong type into an empty one', function () {
        $input = ContactFieldsInput::fromPayload(['phone' => ['required'], 'email' => 1, 'address' => null]);

        expect($input?->phone)->toBe('')
            ->and($input?->email)->toBe('')
            ->and($input?->address)->toBe('');
    });

    it('keeps the value exactly as sent, leaving the judgement to validation', function () {
        expect(ContactFieldsInput::fromPayload([
            ...SettingsFixtures::contactFieldsSection(),
            'phone' => ' REQUIRED ',
        ])?->phone)->toBe(' REQUIRED ');
    });
});

describe('validating', function () {
    it('accepts a section that sets every field to a known requirement', function (string $phone, string $email, string $address) {
        $input = ContactFieldsInput::fromPayload(['phone' => $phone, 'email' => $email, 'address' => $address]);

        expect(fn () => $input?->validate())->not->toThrow(Throwable::class);
    })->with([
        'the defaults' => ['required', 'optional', 'hidden'],
        'everything required' => ['required', 'required', 'required'],
        'everything optional' => ['optional', 'optional', 'optional'],
        'each one different' => ['hidden', 'required', 'optional'],
    ]);

    it('accepts a section built by hand, which names no missing key', function () {
        expect(fn () => SettingsFixtures::contactFieldsInput()->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a section submitted without one of its fields', function (string $absent) {
        $section = SettingsFixtures::contactFieldsSection();
        unset($section[$absent]);

        $refusal = contactFieldsRefusal(ContactFieldsInput::fromPayload($section));

        expect($refusal)->toBeInstanceOf(IncompleteContactFields::class)
            ->and($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('incomplete_contact_fields')
            ->and($refusal->kind())->toBe(DomainFailureKind::Invalid)
            ->and($refusal->getMessage())->toContain($absent);
    })->with([
        'phone' => 'phone',
        'email' => 'email',
        'address' => 'address',
    ]);

    it('refuses a section submitted empty', function () {
        expect(contactFieldsRefusal(ContactFieldsInput::fromPayload([])))
            ->toBeInstanceOf(IncompleteContactFields::class);
    });

    it('names every field the section was missing, not only the first', function () {
        $message = contactFieldsRefusal(ContactFieldsInput::fromPayload(['phone' => 'required']))?->getMessage();

        expect($message)->toContain('email')
            ->and($message)->toContain('address')
            ->and($message)->not->toContain('phone');
    });

    it('refuses a requirement nobody declared, naming the field it was sent for', function (string $field, mixed $value) {
        $refusal = contactFieldsRefusal(ContactFieldsInput::fromPayload([
            ...SettingsFixtures::contactFieldsSection(),
            $field => $value,
        ]));

        expect($refusal)->toBeInstanceOf(InvalidContactFieldRequirement::class)
            ->and($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal->errorCode())->toBe('invalid_contact_field_requirement')
            ->and($refusal->kind())->toBe(DomainFailureKind::Invalid)
            ->and($refusal->getMessage())->toContain('['.$field.']');
    })->with([
        'a phone nobody declared' => ['phone', 'mandatory'],
        'an email left blank' => ['email', ''],
        'an address of spaces' => ['address', '   '],
        'a phone in capitals' => ['phone', 'REQUIRED'],
        'an email padded with spaces' => ['email', ' optional '],
        'an address sent as null' => ['address', null],
        'a phone sent as a boolean' => ['phone', true],
        'an email in another language' => ['email', 'obligatorio'],
    ]);

    it('refuses a missing field before it judges the values of the others', function () {
        expect(contactFieldsRefusal(ContactFieldsInput::fromPayload(['phone' => 'mandatory', 'email' => 'optional'])))
            ->toBeInstanceOf(IncompleteContactFields::class);
    });

    it('judges the phone first, then the email, then the address', function () {
        $refusal = contactFieldsRefusal(new ContactFieldsInput(phone: 'required', email: 'bogus', address: 'nonsense'));

        expect($refusal)->toBeInstanceOf(InvalidContactFieldRequirement::class)
            ->and($refusal?->getMessage())->toContain('[email]')
            ->and($refusal?->getMessage())->not->toContain('[address]');
    });
});
