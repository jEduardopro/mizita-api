<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CustomerPhoneInput;
use App\Domains\Customers\Application\Dtos\UpdateCustomerInput;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Domains\Customers\Exceptions\InvalidCustomerBirthDate;
use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Domains\Customers\Exceptions\InvalidCustomerNotes;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PhoneNumbers;

function updatedCustomerId(): string
{
    return '01930000-0000-7000-8000-0000000000c1';
}

function updateCustomerPayload(array $overrides = []): array
{
    return [
        'name' => 'Ada Byron',
        'email' => 'ada.byron@example.com',
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
        $input = UpdateCustomerInput::fromRequest(updateCustomerPayload(), updatedCustomerId());

        expect($input->customerId)->toBe(updatedCustomerId())
            ->and($input->name)->toBe('Ada Byron')
            ->and($input->email)->toBe('ada.byron@example.com')
            ->and($input->phone)->toBeInstanceOf(CustomerPhoneInput::class)
            ->and($input->birthDate)->toBe('1990-05-17')
            ->and($input->notes)->toBe('Prefers the afternoon.')
            ->and($input->address)->toBeInstanceOf(CustomerAddressSnapshot::class);
    });

    it('takes the customer from the route rather than from the body, which the caller controls', function () {
        $input = UpdateCustomerInput::fromRequest(
            updateCustomerPayload(['customer_id' => '01930000-0000-7000-8000-0000000000ff']),
            updatedCustomerId(),
        );

        expect($input->customerId)->toBe(updatedCustomerId());
    });

    it('survives a body with every key missing', function () {
        $input = UpdateCustomerInput::fromRequest([], updatedCustomerId());

        expect($input->customerId)->toBe(updatedCustomerId())
            ->and($input->name)->toBe('')
            ->and($input->email)->toBeNull()
            ->and($input->phone)->toBeNull()
            ->and($input->birthDate)->toBeNull()
            ->and($input->notes)->toBeNull()
            ->and($input->address)->toBeNull();
    });

    it('turns a body with every key missing into a domain failure rather than a PHP error', function () {
        expect(fn () => UpdateCustomerInput::fromRequest([], updatedCustomerId())->validate())
            ->toThrow(InvalidCustomerName::class);
    });

    it('reads a wrongly typed value as nothing rather than casting it', function (array $payload, string $field) {
        expect(UpdateCustomerInput::fromRequest($payload, updatedCustomerId())->{$field})->toBeNull();
    })->with([
        'email as an array' => [['email' => ['ada@example.com']], 'email'],
        'birth date as a number' => [['birth_date' => 19900517], 'birthDate'],
        'notes as an array' => [['notes' => ['a note']], 'notes'],
        'phone as a string' => [['phone' => '+525512345678'], 'phone'],
        'address as a string' => [['address' => 'Avenida Insurgentes Sur 1602'], 'address'],
        'an empty address section' => [['address' => []], 'address'],
    ]);

    it('reads clearing an optional field as sending none of it', function () {
        $input = UpdateCustomerInput::fromRequest(
            ['name' => 'Ada', 'email' => '', 'birth_date' => '', 'notes' => ''],
            updatedCustomerId(),
        );

        expect($input->email)->toBeNull()
            ->and($input->birthDate)->toBeNull()
            ->and($input->notes)->toBeNull();
    });
});

describe('the customer it is asked to change', function () {
    it('accepts a uuid however it is cased, because the column is case insensitive about it', function (string $id) {
        expect(fn () => UpdateCustomerInput::fromRequest(['name' => 'Ada'], $id)->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'lowercase' => '01930000-0000-7000-8000-0000000000c1',
        'uppercase' => '01930000-0000-7000-8000-0000000000C1',
        'a version four uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    ]);

    it('refuses an id no customer could ever have', function (string $id) {
        expect(fn () => UpdateCustomerInput::fromRequest(['name' => 'Ada'], $id)->validate())
            ->toThrow(CustomerNotFound::class);
    })->with([
        'empty' => '',
        'a word' => 'me',
        'a row number' => '42',
        'a uuid missing a block' => '01930000-0000-7000-0000000000c1',
        'a uuid with braces' => '{01930000-0000-7000-8000-0000000000c1}',
        'a uuid without hyphens' => '019300000000700080000000000000c1',
        'padded' => ' 01930000-0000-7000-8000-0000000000c1 ',
        'a uuid with a letter beyond hexadecimal' => '01930000-0000-7000-8000-0000000000zz',
        'sql' => "01930000-0000-7000-8000-0000000000c1' or '1'='1",
    ]);

    it('refuses a uuid with a line appended, which a loose pattern would have let through', function () {
        expect(fn () => UpdateCustomerInput::fromRequest(
            ['name' => 'Ada'],
            "01930000-0000-7000-8000-0000000000c1\nDROP TABLE customers",
        )->validate())->toThrow(CustomerNotFound::class);
    });

    it('refuses a uuid with a trailing newline, which anchoring alone would have allowed', function () {
        expect(fn () => UpdateCustomerInput::fromRequest(
            ['name' => 'Ada'],
            "01930000-0000-7000-8000-0000000000c1\n",
        )->validate())->toThrow(CustomerNotFound::class);
    });

    it('judges the customer before the payload, so a bad id is a missing page and not a bad name', function () {
        expect(fn () => UpdateCustomerInput::fromRequest(['name' => ''], 'not-a-uuid')->validate())
            ->toThrow(CustomerNotFound::class);
    });

    it('refuses an unknown customer with a failure the responder renders as a missing page', function () {
        $refusal = null;

        try {
            UpdateCustomerInput::fromRequest(['name' => 'Ada'], 'not-a-uuid')->validate();
        } catch (CustomerNotFound $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('customer_not_found')
            ->and($refusal?->kind())->toBe(DomainFailureKind::NotFound);
    });
});

describe('validating the payload', function () {
    it('accepts a payload every rule agrees with', function () {
        expect(fn () => UpdateCustomerInput::fromRequest(updateCustomerPayload(), updatedCustomerId())->validate())
            ->not->toThrow(Throwable::class);
    });

    it('accepts a change that leaves the customer with nothing but a name', function () {
        expect(fn () => UpdateCustomerInput::fromRequest(['name' => 'Ada Byron'], updatedCustomerId())->validate())
            ->not->toThrow(Throwable::class);
    });

    it('holds a change to every rule creating a customer is held to', function (array $payload, string $exception) {
        expect(fn () => UpdateCustomerInput::fromRequest($payload, updatedCustomerId())->validate())
            ->toThrow($exception);
    })->with([
        'a blank name' => [['name' => '   '], InvalidCustomerName::class],
        'a name too long' => [['name' => str_repeat('a', Customer::MAXIMUM_NAME_LENGTH + 1)], InvalidCustomerName::class],
        'an email that is not one' => [['name' => 'Ada', 'email' => 'not-an-email'], InvalidCustomerEmail::class],
        'a birth date that is not a date' => [['name' => 'Ada', 'birth_date' => 'yesterday'], InvalidCustomerBirthDate::class],
        'notes too long' => [
            ['name' => 'Ada', 'notes' => str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH + 1)],
            InvalidCustomerNotes::class,
        ],
        'a phone in no country' => [
            ['name' => 'Ada', 'phone' => ['country_code' => 'MEX', 'national_number' => '5512345678']],
            InvalidCustomerPhone::class,
        ],
    ]);

    it('accepts a name and notes at exactly the length the entity allows', function () {
        expect(fn () => UpdateCustomerInput::fromRequest([
            'name' => str_repeat('a', Customer::MAXIMUM_NAME_LENGTH),
            'notes' => str_repeat('a', Customer::MAXIMUM_NOTES_LENGTH),
        ], updatedCustomerId())->validate())->not->toThrow(Throwable::class);
    });

    it('leaves a birth date in the future to the entity, which is the only place that knows now', function () {
        expect(fn () => UpdateCustomerInput::fromRequest(
            ['name' => 'Ada', 'birth_date' => '2999-01-01'],
            updatedCustomerId(),
        )->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a blank street, which is how a caller says to leave the address alone', function () {
        expect(fn () => UpdateCustomerInput::fromRequest(
            ['name' => 'Ada', 'address' => ['street' => '   ', 'country_code' => 'MX']],
            updatedCustomerId(),
        )->validate())->not->toThrow(Throwable::class);
    });
});

describe('turning the birth date into an instant', function () {
    it('hands the entity the calendar date the caller typed', function () {
        expect(UpdateCustomerInput::fromRequest(['birth_date' => '1990-05-17'], updatedCustomerId())->toBirthDate())
            ->toEqual(new DateTimeImmutable('1990-05-17 00:00:00'));
    });

    it('hands the entity no date when the caller cleared it', function () {
        expect(UpdateCustomerInput::fromRequest(['birth_date' => ''], updatedCustomerId())->toBirthDate())
            ->toBeNull();
    });

    it('refuses rather than handing the entity a date it invented', function () {
        expect(fn () => UpdateCustomerInput::fromRequest(['birth_date' => '1990-02-30'], updatedCustomerId())->toBirthDate())
            ->toThrow(InvalidCustomerBirthDate::class);
    });
});
