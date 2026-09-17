<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\CustomerPhoneInput;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PhoneNumbers;

describe('reading the phone section of a payload', function () {
    it('assembles itself from the section the form request would have passed', function () {
        $phone = CustomerPhoneInput::fromPayload([
            'country_code' => 'MX',
            'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER,
        ]);

        expect($phone?->countryCode)->toBe('MX')
            ->and($phone?->nationalNumber)->toBe(PhoneNumbers::MX_NATIONAL_NUMBER);
    });

    it('reads a customer who gave no phone as having none', function (mixed $section) {
        expect(CustomerPhoneInput::fromPayload($section))->toBeNull();
    })->with([
        'missing' => null,
        'an empty section' => [[]],
        'a bare string' => '+525512345678',
        'a number' => 5512345678,
        'a boolean' => false,
    ]);

    it('fills in a half sent section rather than failing to read it', function (array $section, string $countryCode, string $nationalNumber) {
        $phone = CustomerPhoneInput::fromPayload($section);

        expect($phone?->countryCode)->toBe($countryCode)
            ->and($phone?->nationalNumber)->toBe($nationalNumber);
    })->with([
        'a country and nothing else' => [['country_code' => 'MX'], 'MX', ''],
        'a number and nothing else' => [['national_number' => '5512345678'], '', '5512345678'],
        'both wrongly typed' => [['country_code' => 52, 'national_number' => ['5512345678']], '', ''],
    ]);

    it('holds what it was handed without normalising it, because the parser is what dials', function () {
        $phone = CustomerPhoneInput::fromPayload([
            'country_code' => '  mx  ',
            'national_number' => '  55 1234 5678  ',
        ]);

        expect($phone?->countryCode)->toBe('  mx  ')
            ->and($phone?->nationalNumber)->toBe('  55 1234 5678  ');
    });
});

describe('validating', function () {
    it('accepts a phone the parser can be asked about', function () {
        expect(fn () => CustomerPhoneInput::fromPayload([
            'country_code' => 'MX',
            'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER,
        ])?->validate())->not->toThrow(Throwable::class);
    });

    it('accepts a country code however it is spaced or cased, which the parser folds', function (string $countryCode) {
        expect(fn () => (new CustomerPhoneInput($countryCode, PhoneNumbers::MX_NATIONAL_NUMBER))->validate())
            ->not->toThrow(Throwable::class);
    })->with(['uppercase' => 'MX', 'lowercase' => 'mx', 'padded' => '  MX  ']);

    it('refuses a country code that is not two letters', function (string $countryCode) {
        expect(fn () => (new CustomerPhoneInput($countryCode, PhoneNumbers::MX_NATIONAL_NUMBER))->validate())
            ->toThrow(InvalidCustomerPhone::class, 'is not a country we can dial');
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'one letter' => 'M',
        'three letters' => 'MEX',
        'a dial code' => '+52',
        'a single digit' => '5',
    ]);

    it('refuses a number nobody typed', function (string $nationalNumber) {
        expect(fn () => (new CustomerPhoneInput('MX', $nationalNumber))->validate())
            ->toThrow(InvalidCustomerPhone::class, 'cannot be dialled in [MX]');
    })->with(['empty' => '', 'spaces' => '   ', 'tab' => "\t"]);

    it('accepts a number as long as the column holds', function () {
        expect(fn () => (new CustomerPhoneInput(
            'MX',
            str_repeat('5', CustomerPhoneInput::MAXIMUM_NATIONAL_NUMBER_LENGTH),
        ))->validate())->not->toThrow(Throwable::class);
    });

    it('refuses a number one character past what the column holds', function () {
        expect(fn () => (new CustomerPhoneInput(
            'MX',
            str_repeat('5', CustomerPhoneInput::MAXIMUM_NATIONAL_NUMBER_LENGTH + 1),
        ))->validate())->toThrow(InvalidCustomerPhone::class);
    });

    it('measures the number after trimming it', function () {
        expect(fn () => (new CustomerPhoneInput(
            'MX',
            '  '.str_repeat('5', CustomerPhoneInput::MAXIMUM_NATIONAL_NUMBER_LENGTH).'  ',
        ))->validate())->not->toThrow(Throwable::class);
    });

    it('judges the country before the number, so an unreachable country is named as such', function () {
        expect(fn () => (new CustomerPhoneInput('MEX', ''))->validate())
            ->toThrow(InvalidCustomerPhone::class, 'is not a country we can dial');
    });

    it('leaves to the parser whether a well shaped number can really be dialled', function () {
        expect(fn () => (new CustomerPhoneInput('ZZ', '0000000'))->validate())
            ->not->toThrow(Throwable::class);
    });

    it('leaves to the parser whether two characters really name a country we operate in', function (string $countryCode) {
        expect(fn () => (new CustomerPhoneInput($countryCode, PhoneNumbers::MX_NATIONAL_NUMBER))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'a country nobody is assigned' => 'ZZ',
        'a dial code two digits long' => '52',
        'two symbols' => '??',
    ]);

    it('refuses with a failure the responder can classify', function () {
        $refusal = null;

        try {
            (new CustomerPhoneInput('MEX', '5512345678'))->validate();
        } catch (InvalidCustomerPhone $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_customer_phone')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    });
});
