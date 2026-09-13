<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use App\Domains\Businesses\ValueObjects\Timezone;

/*
| Pure PHP. The zone is the single source of local time for every hour a
| business ever publishes, so what is asserted is the exact identifier stored -
| a zone that resolves to the right offset but is spelled differently would
| fail every later comparison.
*/

describe('accepting an identifier', function () {
    it('accepts an IANA identifier and stores it verbatim', function (string $identifier) {
        expect(Timezone::fromString($identifier)->value)->toBe($identifier);
    })->with([
        'the product home zone' => 'Europe/Madrid',
        'the seed default' => 'America/Mexico_City',
        'three segments' => 'America/Argentina/Buenos_Aires',
        'an underscore' => 'America/New_York',
        'UTC' => 'UTC',
    ]);

    it('accepts a backward compatible alias, spelled the way it arrived', function (string $alias) {
        // A browser reporting Asia/Calcutta is reporting a zone that works, and
        // refusing it would lock that person out of onboarding. PHP hands the
        // identifier back as given, so the alias is not silently rewritten to
        // its modern name either.
        expect(Timezone::fromString($alias)->value)->toBe($alias);
    })->with([
        'Asia/Calcutta' => 'Asia/Calcutta',
        'America/Buenos_Aires' => 'America/Buenos_Aires',
        'Asia/Saigon' => 'Asia/Saigon',
    ]);

    it('trims what the caller padded', function () {
        expect(Timezone::fromString('  Europe/Madrid  ')->value)->toBe('Europe/Madrid');
    });
});

describe('refusing an identifier', function () {
    it('refuses the wrong case, because matching is exact', function (string $value) {
        // DateTimeZone's constructor is case insensitive where the identifier
        // list is not. Constructing without the membership check would store
        // the string as typed, and every later comparison against the canonical
        // spelling would fail.
        expect(fn () => Timezone::fromString($value))
            ->toThrow(InvalidBusinessTimezone::class, "[{$value}] is not a valid IANA time zone identifier.");
    })->with([
        'lower case' => 'europe/madrid',
        'upper case' => 'EUROPE/MADRID',
        'mixed' => 'Europe/madrid',
    ]);

    it('refuses anything that is not an identifier at all', function (string $value) {
        expect(fn () => Timezone::fromString($value))
            ->toThrow(InvalidBusinessTimezone::class, "[{$value}] is not a valid IANA time zone identifier.");
    })->with([
        'empty' => '',
        'whitespace' => '   ',
        'a fixed offset' => '+02:00',
        'an abbreviation' => 'CEST',
        'a place that is not a zone' => 'Mars/Olympus',
        'a city with no region' => 'Madrid',
        'a wrong separator' => 'Europe\\Madrid',
    ]);

    it('names the value the caller sent, not the trimmed one, so the message matches what they typed', function () {
        expect(fn () => Timezone::fromString('  Mars/Olympus  '))
            ->toThrow(InvalidBusinessTimezone::class, '[  Mars/Olympus  ] is not a valid IANA time zone identifier.');
    });
});

describe('rehydrating from storage', function () {
    it('accepts a stored value fromString would refuse', function (string $stored) {
        // A tzdata update is enough to retire an identifier. Failing here would
        // make the business impossible to load at all, rather than merely wrong
        // about its opening hours - so the check lives on the way in.
        expect(Timezone::restore($stored)->value)->toBe($stored);
    })->with([
        'a retired identifier' => 'Europe/Atlantis',
        'the wrong case' => 'europe/madrid',
        'empty' => '',
    ]);
});

describe('equality', function () {
    it('compares by value', function () {
        expect(Timezone::fromString('Europe/Madrid')->equals(Timezone::fromString('Europe/Madrid')))->toBeTrue()
            ->and(Timezone::fromString('Europe/Madrid')->equals(Timezone::fromString('America/Mexico_City')))->toBeFalse();
    });

    it('treats an alias and the zone it points at as different values', function () {
        // Same instants, different identifiers. Equality is about the stored
        // string, because that is what a later conversion reads.
        expect(Timezone::fromString('Asia/Calcutta')->equals(Timezone::fromString('Asia/Kolkata')))->toBeFalse();
    });
});
