<?php

declare(strict_types=1);

use App\Domains\Businesses\Application\Dtos\LocationInput;
use App\Domains\Businesses\Exceptions\InvalidBusinessCoordinates;
use App\Domains\Businesses\Exceptions\InvalidBusinessCurrency;
use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Businesses\SettingsFixtures;

it('accepts a location filled in as the form paints it', function () {
    expect(fn () => SettingsFixtures::location()->validate())->not->toThrow(Throwable::class);
});

it('holds the place it was handed without normalising it, because the use case trims on the way out', function () {
    $location = SettingsFixtures::location(street: '  Avenida Insurgentes Sur 1602  ');

    expect($location->street)->toBe('  Avenida Insurgentes Sur 1602  ')
        ->and($location->stateId)->toBe(SettingsFixtures::STATE_ID);
});

it('leaves the street, the city and the postal code to the address that owns those rules', function () {
    expect(fn () => SettingsFixtures::location(street: '', city: '', postalCode: '')->validate())
        ->not->toThrow(Throwable::class);
});

describe('the currency', function () {
    it('accepts a three letter code in any case, because the value object folds it', function (string $code) {
        expect(fn () => SettingsFixtures::location(currencyCode: $code)->validate())->not->toThrow(Throwable::class);
    })->with([
        'uppercase' => 'MXN',
        'lowercase' => 'usd',
        'mixed case' => 'EuR',
        'padded' => '  MXN  ',
    ]);

    it('refuses anything that is not a three letter code', function (string $code) {
        expect(fn () => SettingsFixtures::location(currencyCode: $code)->validate())
            ->toThrow(InvalidBusinessCurrency::class);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'two letters' => 'MX',
        'four letters' => 'MXNN',
        'digits' => '840',
        'a symbol' => '$',
    ]);
});

describe('the timezone', function () {
    it('accepts an IANA zone', function (string $timezone) {
        expect(fn () => SettingsFixtures::location(timezone: $timezone)->validate())->not->toThrow(Throwable::class);
    })->with([
        'Madrid' => 'Europe/Madrid',
        'Mexico City' => 'America/Mexico_City',
        'UTC' => 'UTC',
    ]);

    it('refuses anything a business could not keep local time by', function (string $timezone) {
        expect(fn () => SettingsFixtures::location(timezone: $timezone)->validate())
            ->toThrow(InvalidBusinessTimezone::class);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a fixed offset' => '+02:00',
        'a zone nobody keeps' => 'Mars/Olympus_Mons',
        'an abbreviation' => 'CEST',
    ]);
});

describe('the coordinates', function () {
    it('accepts a business that pins itself nowhere', function () {
        expect(fn () => SettingsFixtures::location(latitude: null, longitude: null)->validate())
            ->not->toThrow(Throwable::class);
    });

    it('refuses half a point, because one degree alone names no place', function (?string $latitude, ?string $longitude) {
        expect(fn () => SettingsFixtures::location(latitude: $latitude, longitude: $longitude)->validate())
            ->toThrow(InvalidBusinessCoordinates::class, 'A latitude and a longitude are given together or not at all.');
    })->with([
        'a latitude alone' => [SettingsFixtures::LATITUDE, null],
        'a longitude alone' => [null, SettingsFixtures::LONGITUDE],
    ]);

    it('accepts the ends of the world a coordinate may name', function (string $latitude, string $longitude) {
        expect(fn () => SettingsFixtures::location(latitude: $latitude, longitude: $longitude)->validate())
            ->not->toThrow(Throwable::class);
    })->with([
        'the south pole' => ['-90', '0'],
        'the north pole' => ['90', '0'],
        'the antimeridian going west' => ['0', '-180'],
        'the antimeridian going east' => ['0', '180'],
        'null island deliberately named' => ['0', '0'],
        'both extremes at once' => ['-90', '-180'],
        'every decimal the column stores' => ['19.3627888', '-99.1768069'],
        'padded with spaces' => ['  19.3627888  ', '  -99.1768069  '],
    ]);

    it('refuses a latitude no place on earth has', function (string $latitude) {
        expect(fn () => SettingsFixtures::location(latitude: $latitude)->validate())
            ->toThrow(InvalidBusinessCoordinates::class, 'range a latitude lives in');
    })->with([
        'a hair past the south pole' => '-90.0000001',
        'a hair past the north pole' => '90.0000001',
        'a whole degree past the north pole' => '91',
        'a longitude mistaken for a latitude' => '180',
        'nonsense' => '1000',
    ]);

    it('refuses a longitude no place on earth has', function (string $longitude) {
        expect(fn () => SettingsFixtures::location(longitude: $longitude)->validate())
            ->toThrow(InvalidBusinessCoordinates::class, 'range a longitude lives in');
    })->with([
        'a hair past the antimeridian going west' => '-180.0000001',
        'a hair past the antimeridian going east' => '180.0000001',
        'a whole degree past the antimeridian' => '181',
        'nonsense' => '-1000',
    ]);

    it('refuses a coordinate that is not a number, instead of letting a cast land it on Null Island', function (string $junk) {
        expect(fn () => SettingsFixtures::location(latitude: $junk)->validate())
            ->toThrow(InvalidBusinessCoordinates::class, "[{$junk}] is not a decimal coordinate.");
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'a word' => 'north',
        'a decimal comma' => '19,3627888',
        'a degree sign' => '19.3627888°',
        'a number with a trailing letter' => '19.3627888N',
        'hexadecimal' => '0x1A',
        'not a number' => 'NaN',
        'infinity spelled out' => 'INF',
        'a lone minus' => '-',
        'a lone dot' => '.',
    ]);

    it('refuses a longitude that is not a number, even when the latitude reads fine', function () {
        expect(fn () => SettingsFixtures::location(longitude: 'west')->validate())
            ->toThrow(InvalidBusinessCoordinates::class, '[west] is not a decimal coordinate.');
    });

    it('judges the raw string rather than what a cast would make of it', function () {
        expect((float) 'north')->toBe(0.0)
            ->and(fn () => SettingsFixtures::location(latitude: 'north', longitude: 'west')->validate())
            ->toThrow(InvalidBusinessCoordinates::class);
    });
});

it('names the currency first when everything is wrong at once', function () {
    expect(fn () => SettingsFixtures::location(
        latitude: 'north',
        currencyCode: 'nope',
        timezone: 'Mars/Olympus_Mons',
    )->validate())->toThrow(InvalidBusinessCurrency::class);
});

it('refuses with a failure the responder can classify', function () {
    $refusal = null;

    try {
        SettingsFixtures::location(latitude: 'north')->validate();
    } catch (InvalidBusinessCoordinates $caught) {
        $refusal = $caught;
    }

    expect($refusal)->toBeInstanceOf(DomainFailure::class)
        ->and($refusal?->errorCode())->toBe('invalid_business_coordinates')
        ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
});

it('bounds a coordinate by the constants the client and the column share', function () {
    expect(LocationInput::MINIMUM_LATITUDE)->toBe(-90.0)
        ->and(LocationInput::MAXIMUM_LATITUDE)->toBe(90.0)
        ->and(LocationInput::MINIMUM_LONGITUDE)->toBe(-180.0)
        ->and(LocationInput::MAXIMUM_LONGITUDE)->toBe(180.0);
});
