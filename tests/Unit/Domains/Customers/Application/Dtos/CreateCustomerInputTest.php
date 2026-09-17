<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CreateCustomerInput;
use App\Domains\Customers\Application\Dtos\CustomerPhoneInput;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\InvalidCustomerBirthDate;
use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerNotes;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PhoneNumbers;

function createCustomerPayload(array $overrides = []): array
{
    return [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'phone' => ['country_code' => 'MX', 'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER],
        'birth_date' => '1990-05-17',
        'notes' => 'Prefers the afternoon.',
        'address' => [
            'street' => 'Avenida Insurgentes Sur 1602',
            'city' => 'Ciudad de México',
            'state_id' => '01930000-0000-7000-8000-0000000000e1',
            'postal_code' => '03940',
            'country_code' => 'MX',
        ],
        ...$overrides,
    ];
}

describe('reading a payload', function () {
    it('assembles itself from a body the form request would have passed', function () {
        $input = CreateCustomerInput::fromRequest(createCustomerPayload());

        expect($input->name)->toBe('Ada Lovelace')
            ->and($input->email)->toBe('ada@example.com')
            ->and($input->phone)->toBeInstanceOf(CustomerPhoneInput::class)
            ->and($input->phone?->countryCode)->toBe('MX')
            ->and($input->phone?->nationalNumber)->toBe(PhoneNumbers::MX_NATIONAL_NUMBER)
            ->and($input->birthDate)->toBe('1990-05-17')
            ->and($input->notes)->toBe('Prefers the afternoon.')
            ->and($input->address)->toBeInstanceOf(CustomerAddressSnapshot::class);
    });

    it('assembles the address the caller nested inside the body', function () {
        $address = CreateCustomerInput::fromRequest(createCustomerPayload())->address;

        expect($address?->street)->toBe('Avenida Insurgentes Sur 1602')
            ->and($address?->city)->toBe('Ciudad de México')
            ->and($address?->stateId)->toBe('01930000-0000-7000-8000-0000000000e1')
            ->and($address?->postalCode)->toBe('03940')
            ->and($address?->countryCode)->toBe('MX');
    });

    it('survives a body with every key missing, because a caller who skipped the form request has none', function () {
        $input = CreateCustomerInput::fromRequest([]);

        expect($input->name)->toBe('')
            ->and($input->email)->toBeNull()
            ->and($input->phone)->toBeNull()
            ->and($input->birthDate)->toBeNull()
            ->and($input->notes)->toBeNull()
            ->and($input->address)->toBeNull();
    });

    it('turns a body with every key missing into a domain failure rather than a PHP error', function () {
        expect(fn () => CreateCustomerInput::fromRequest([])->validate())
            ->toThrow(InvalidCustomerName::class);
    });

    it('reads a wrongly typed value as nothing rather than casting it', function (array $payload, string $field) {
        expect(CreateCustomerInput::fromRequest($payload)->{$field})->toBeNull();
    })->with([
        'email as an array' => [['email' => ['ada@example.com']], 'email'],
        'email as a number' => [['email' => 42], 'email'],
        'birth date as an array' => [['birth_date' => ['1990-05-17']], 'birthDate'],
        'notes as a number' => [['notes' => 42], 'notes'],
        'phone as a string' => [['phone' => '+525512345678'], 'phone'],
        'phone as an empty array' => [['phone' => []], 'phone'],
        'address as a string' => [['address' => 'Avenida Insurgentes Sur 1602'], 'address'],
        'address as an empty array' => [['address' => []], 'address'],
    ]);

    it('reads a name that is not a string as no name at all', function (mixed $name) {
        expect(CreateCustomerInput::fromRequest(['name' => $name])->name)->toBe('');
    })->with([
        'null' => null,
        'a number' => 42,
        'an array' => [['Ada']],
        'a boolean' => true,
    ]);

    it('reads an optional value that says nothing as none at all', function (string $key, string $blank, string $field) {
        expect(CreateCustomerInput::fromRequest([$key => $blank])->{$field})->toBeNull();
    })->with([
        'a blank email' => ['email', '   ', 'email'],
        'an empty email' => ['email', '', 'email'],
        'a blank birth date' => ['birth_date', '   ', 'birthDate'],
        'blank notes' => ['notes', '   ', 'notes'],
        'a tab for notes' => ['notes', "\t", 'notes'],
    ]);

    it('holds the name it was handed without normalising it, because the entity trims on the way in', function () {
        expect(CreateCustomerInput::fromRequest(['name' => '  Ada Lovelace  '])->name)
            ->toBe('  Ada Lovelace  ');
    });

    it('holds an optional value it keeps exactly as it arrived', function () {
        $input = CreateCustomerInput::fromRequest(['name' => 'Ada', 'email' => '  ada@example.com  ']);

        expect($input->email)->toBe('  ada@example.com  ');
    });

    it('fills in an address the caller only half sent, leaving the rules to the address domain', function () {
        $address = CreateCustomerInput::fromRequest(['address' => ['city' => 'Ciudad de México']])->address;

        expect($address?->street)->toBe('')
            ->and($address?->countryCode)->toBe('')
            ->and($address?->city)->toBe('Ciudad de México')
            ->and($address?->stateId)->toBeNull()
            ->and($address?->postalCode)->toBeNull();
    });

    it('fills in a phone the caller only half sent, leaving the refusal to validation', function () {
        $phone = CreateCustomerInput::fromRequest(['phone' => ['country_code' => 'MX']])->phone;

        expect($phone?->countryCode)->toBe('MX')
            ->and($phone?->nationalNumber)->toBe('');
    });
});

describe('validating', function () {
    it('accepts a payload every rule agrees with', function () {
        expect(fn () => CreateCustomerInput::fromRequest(createCustomerPayload())->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts a customer who gave nothing but a name', function () {
        expect(fn () => CreateCustomerInput::fromRequest(['name' => 'Ada Lovelace'])->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses a name that says nothing', function (string $name) {
        expect(fn () => CreateCustomerInput::fromRequest(['name' => $name])->validate())
            ->toThrow(InvalidCustomerName::class);
    })->with(['empty' => '', 'spaces' => '   ', 'tab' => "\t", 'newline' => "\n"]);

    it('accepts a name as long as the entity allows', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => str_repeat('a', Customer::MAXIMUM_NAME_LENGTH),
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a name one character past what the entity allows', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => str_repeat('a', Customer::MAXIMUM_NAME_LENGTH + 1),
        ])->validate())->toThrow(InvalidCustomerName::class);
    });

    it('measures the name after trimming it, exactly as the entity will', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => '  '.str_repeat('a', Customer::MAXIMUM_NAME_LENGTH).'  ',
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('counts the characters of a name rather than its bytes', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => str_repeat('á', Customer::MAXIMUM_NAME_LENGTH),
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('refuses an email that is not one', function (string $email) {
        expect(fn () => CreateCustomerInput::fromRequest(['name' => 'Ada', 'email' => $email])->validate())
            ->toThrow(InvalidCustomerEmail::class);
    })->with([
        'no sign' => 'ada.example.com',
        'no domain' => 'ada@',
        'a space inside' => 'ada lovelace@example.com',
        'a bare word' => 'ada',
        'too long' => str_repeat('a', 250).'@example.com',
    ]);

    it('reads a blank email built straight onto the DTO as none rather than refusing it', function () {
        $input = new CreateCustomerInput('Ada', '   ', null, null, null, null);

        expect(fn () => $input->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a birth date that is not a calendar date', function (string $birthDate) {
        expect(fn () => CreateCustomerInput::fromRequest(['name' => 'Ada', 'birth_date' => $birthDate])->validate())
            ->toThrow(InvalidCustomerBirthDate::class);
    })->with([
        'day first' => '17/05/1990',
        'a word' => 'yesterday',
        'a day the calendar lacks' => '1990-02-30',
        'a timestamp' => '1990-05-17T00:00:00',
    ]);

    it('leaves a birth date in the future to the entity, which is the only place that knows now', function () {
        expect(fn () => CreateCustomerInput::fromRequest(['name' => 'Ada', 'birth_date' => '2999-01-01'])->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts notes as long as the entity allows', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => 'Ada',
            'notes' => str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH),
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('refuses notes one character past what the entity allows', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => 'Ada',
            'notes' => str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH + 1),
        ])->validate())->toThrow(InvalidCustomerNotes::class);
    });

    it('measures notes after trimming them', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => 'Ada',
            'notes' => '  '.str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH).'  ',
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('holds the phone to the rules the phone input owns', function (array $phone) {
        expect(fn () => CreateCustomerInput::fromRequest(['name' => 'Ada', 'phone' => $phone])->validate())
            ->toThrow(InvalidCustomerPhone::class);
    })->with([
        'a country that is not two letters' => [['country_code' => 'MEX', 'national_number' => '5512345678']],
        'a number nobody typed' => [['country_code' => 'MX', 'national_number' => '   ']],
        'a country the caller left out' => [['national_number' => '5512345678']],
    ]);

    it('skips the phone rules altogether when no phone was sent', function () {
        expect(fn () => CreateCustomerInput::fromRequest(['name' => 'Ada'])->validate())
            ->not->toThrow(Throwable::class);
    });

    it('names the name first when everything is wrong at once', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => '',
            'email' => 'not-an-email',
            'birth_date' => 'yesterday',
            'phone' => ['country_code' => 'MEX', 'national_number' => ''],
        ])->validate())->toThrow(InvalidCustomerName::class);
    });

    it('judges the email before the birth date', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => 'Ada',
            'email' => 'not-an-email',
            'birth_date' => 'yesterday',
        ])->validate())->toThrow(InvalidCustomerEmail::class);
    });

    it('refuses with a failure the responder can classify', function () {
        $refusal = null;

        try {
            CreateCustomerInput::fromRequest(['name' => ''])->validate();
        } catch (InvalidCustomerName $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_customer_name')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    });
});

describe('the address it deliberately does not judge', function () {
    it('accepts a blank street, which is how a caller says to leave the address alone', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => 'Ada',
            'address' => ['street' => '   ', 'country_code' => 'MX'],
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('accepts an address with nothing in it but the keys, leaving every rule to the address domain', function () {
        expect(fn () => CreateCustomerInput::fromRequest([
            'name' => 'Ada',
            'address' => ['city' => '', 'postal_code' => '', 'country_code' => 'nowhere'],
        ])->validate())->not->toThrow(Throwable::class);
    });

    it('keeps a blank street rather than dropping the section, because the use case reads it as the signal', function () {
        $address = CreateCustomerInput::fromRequest([
            'address' => ['street' => '   ', 'country_code' => 'MX'],
        ])->address;

        expect($address)->toBeInstanceOf(CustomerAddressSnapshot::class)
            ->and($address?->street)->toBe('   ');
    });
});

describe('turning the birth date into an instant', function () {
    it('hands the entity the calendar date the caller typed', function () {
        expect(CreateCustomerInput::fromRequest(['birth_date' => '1990-05-17'])->toBirthDate())
            ->toEqual(new DateTimeImmutable('1990-05-17 00:00:00'));
    });

    it('hands the entity no date when the caller gave none', function () {
        expect(CreateCustomerInput::fromRequest([])->toBirthDate())->toBeNull();
    });

    it('refuses rather than handing the entity a date it invented', function () {
        expect(fn () => CreateCustomerInput::fromRequest(['birth_date' => '1990-02-30'])->toBirthDate())
            ->toThrow(InvalidCustomerBirthDate::class);
    });
});
