<?php

declare(strict_types=1);

use App\Domains\Addresses\Application\Dtos\ListStatesInput;
use App\Domains\Addresses\Exceptions\UnsupportedCountry;
use App\Shared\ValueObjects\CountryCode;

describe('building the input from a payload', function () {
    it('reads the country the caller asked for', function () {
        expect(ListStatesInput::fromRequest(['country' => 'US'])->countryCode)->toBe('US');
    });

    it('folds the country to the case the catalogue stores', function (string $given) {
        expect(ListStatesInput::fromRequest(['country' => $given])->countryCode)->toBe('MX');
    })->with([
        'lower case' => 'mx',
        'mixed case' => 'Mx',
        'padded' => '  mx  ',
        'padded with a tab' => "\tMX\n",
    ]);

    it('falls back to Mexico when the caller named no country', function (array $payload) {
        expect(ListStatesInput::fromRequest($payload)->countryCode)->toBe(CountryCode::Mx->value);
    })->with([
        'a payload with no key at all' => [[]],
        'an explicit null' => [['country' => null]],
        'an empty string' => [['country' => '']],
        'whitespace' => [['country' => '   ']],
        'a value that is not text' => [['country' => ['MX']]],
        'a number' => [['country' => 52]],
    ]);

    it('survives a caller who never saw the form request', function () {
        expect(fn () => ListStatesInput::fromRequest([])->validate())->not->toThrow(Throwable::class);
    });
});

describe('validating the input', function () {
    it('accepts every country the platform operates in', function (CountryCode $country) {
        expect(fn () => ListStatesInput::fromRequest(['country' => $country->value])->validate())
            ->not->toThrow(Throwable::class);
    })->with(fn () => CountryCode::cases());

    it('refuses a country the platform does not operate in', function (string $country) {
        expect(fn () => (new ListStatesInput($country))->validate())->toThrow(UnsupportedCountry::class);
    })->with([
        'a country we have not opened yet' => 'ES',
        'a made up code' => 'ZZ',
        'one letter' => 'M',
        'three letters' => 'MEX',
        'the country name' => 'México',
        'empty' => '',
        'lower case, because the constructor normalises nothing' => 'mx',
        'padded' => ' MX ',
    ]);

    it('quotes the code it refused', function () {
        expect(fn () => (new ListStatesInput('ZZ'))->validate())
            ->toThrow(UnsupportedCountry::class, '[ZZ] is not a country this platform operates in.');
    });
});

describe('reading the country back', function () {
    it('hands the catalogue an enum rather than a string', function () {
        expect(ListStatesInput::fromRequest(['country' => 'us'])->country())->toBe(CountryCode::Us);
    });

    it('fails loudly when it is asked before the input was validated', function () {
        expect(fn () => (new ListStatesInput('ZZ'))->country())->toThrow(ValueError::class);
    });
});
