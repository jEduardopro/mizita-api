<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\ProfilePhoneInput;
use App\Domains\Staff\Exceptions\InvalidProfilePhone;
use App\Shared\Contracts\DomainFailure;
use Tests\Support\PhoneNumbers;

describe('reading the phone section of a payload', function () {
    it('assembles itself from the section the form request would have passed', function () {
        $phone = ProfilePhoneInput::fromPayload([
            'country_code' => 'MX',
            'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER,
        ]);

        expect($phone?->countryCode)->toBe('MX')
            ->and($phone?->nationalNumber)->toBe(PhoneNumbers::MX_NATIONAL_NUMBER);
    });

    it('reads a section that is not there as no phone', function (mixed $section) {
        expect(ProfilePhoneInput::fromPayload($section))->toBeNull();
    })->with([
        'missing' => null,
        'an empty section' => [[]],
        'a bare string' => '+525512345678',
        'a number' => 5512345678,
        'a boolean' => false,
    ]);

    it('fills in a half sent section rather than failing to read it', function (array $section, string $countryCode, string $nationalNumber) {
        $phone = ProfilePhoneInput::fromPayload($section);

        expect($phone?->countryCode)->toBe($countryCode)
            ->and($phone?->nationalNumber)->toBe($nationalNumber);
    })->with([
        'a country and nothing else' => [['country_code' => 'MX'], 'MX', ''],
        'a number and nothing else' => [['national_number' => '5512345678'], '', '5512345678'],
        'both wrongly typed' => [['country_code' => 52, 'national_number' => ['5512345678']], '', ''],
    ]);

    it('holds what it was handed without normalising it, because the parser is what dials', function () {
        $phone = ProfilePhoneInput::fromPayload(['country_code' => '  mx  ', 'national_number' => ' 55 1234 5678 ']);

        expect($phone?->countryCode)->toBe('  mx  ')
            ->and($phone?->nationalNumber)->toBe(' 55 1234 5678 ');
    });
});

describe('validating itself', function () {
    it('returns silently for a well formed section', function (string $countryCode, string $nationalNumber) {
        expect(fn () => (new ProfilePhoneInput($countryCode, $nationalNumber))->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'as sent by the form' => ['MX', PhoneNumbers::MX_NATIONAL_NUMBER],
        'a lowercase country' => ['mx', PhoneNumbers::MX_NATIONAL_NUMBER],
        'a padded country' => [' US ', PhoneNumbers::US_NATIONAL_NUMBER],
        'a number at the limit' => ['MX', str_repeat('5', ProfilePhoneInput::MAXIMUM_NATIONAL_NUMBER_LENGTH)],
    ]);

    it('refuses a section the form request would have refused', function (string $countryCode, string $nationalNumber) {
        expect(fn () => (new ProfilePhoneInput($countryCode, $nationalNumber))->validate())
            ->toThrow(InvalidProfilePhone::class);
    })->with([
        'no country' => ['', '5512345678'],
        'a one letter country' => ['M', '5512345678'],
        'a three letter country' => ['MEX', '5512345678'],
        'a dial code for a country' => ['+52', '5512345678'],
        'no number' => ['MX', ''],
        'a whitespace-only number' => ['MX', " \t "],
        'a number one past the limit' => ['MX', str_repeat('5', ProfilePhoneInput::MAXIMUM_NATIONAL_NUMBER_LENGTH + 1)],
    ]);

    it('refuses through a domain failure the renderer understands', function () {
        try {
            (new ProfilePhoneInput('MEX', '5512345678'))->validate();
            $thrown = null;
        } catch (Throwable $failure) {
            $thrown = $failure;
        }

        expect($thrown)->toBeInstanceOf(DomainFailure::class);
    });

    it('checks the country before the number', function () {
        expect(fn () => (new ProfilePhoneInput('MEX', ''))->validate())
            ->toThrow(InvalidProfilePhone::class, '[MEX] is not a country we can dial.');
    });

    it('never quotes the number it refused', function () {
        try {
            (new ProfilePhoneInput('MX', str_repeat('5', 30)))->validate();
            $message = '';
        } catch (InvalidProfilePhone $failure) {
            $message = $failure->getMessage();
        }

        expect($message)->not->toBe('')
            ->and($message)->not->toContain(str_repeat('5', 30));
    });
});
