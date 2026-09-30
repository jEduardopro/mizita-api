<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\InvalidCustomerRegistrationPeriod;
use App\Domains\Customers\ValueObjects\LocalDate;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

describe('reading a calendar date', function () {
    it('reads back the calendar date it was given', function (string $raw) {
        expect(LocalDate::fromString($raw)->toString())->toBe($raw);
    })->with([
        'an ordinary day' => '2026-03-01',
        'the last day of the year' => '2026-12-31',
        'a leap day' => '2028-02-29',
        'a spring forward day' => '2026-03-29',
        'a fall back day' => '2026-10-25',
    ]);

    it('tolerates whitespace around the date', function (string $raw) {
        expect(LocalDate::fromString($raw)->toString())->toBe('2026-03-01');
    })->with(['spaces' => '  2026-03-01  ', 'tab' => "\t2026-03-01", 'newline' => "2026-03-01\n"]);
});

describe('refusing what is not a calendar date', function () {
    it('refuses anything that is not the YYYY-MM-DD form', function (string $raw) {
        expect(fn () => LocalDate::fromString($raw))
            ->toThrow(InvalidCustomerRegistrationPeriod::class, 'is not a calendar date in the YYYY-MM-DD form');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'no padding on the month and day' => '2026-3-1',
        'no padding on the day' => '2026-03-1',
        'no padding on the month' => '2026-3-01',
        'day first' => '01/03/2026',
        'slashes' => '2026/03/01',
        'dotted' => '2026.03.01',
        'a two digit year' => '26-03-01',
        'no separators' => '20260301',
        'only a year and a month' => '2026-03',
        'a timestamp' => '2026-03-01T00:00:00',
        'a date with a time' => '2026-03-01 10:00',
        'a date with an offset' => '2026-03-01+02:00',
        'a signed year' => '+2026-03-01',
        'a five digit year' => '99999-01-01',
        'a word' => 'yesterday',
        'sql injection' => "2026-03-01'; drop table customers",
    ]);

    it('refuses a day the calendar does not have, rather than rolling it over', function (string $raw) {
        expect(fn () => LocalDate::fromString($raw))->toThrow(InvalidCustomerRegistrationPeriod::class);
    })->with([
        'the thirtieth of February' => '2026-02-30',
        'the twenty-ninth of a February that is not a leap year' => '2026-02-29',
        'the thirty-first of April' => '2026-04-31',
        'the thirty-second of January' => '2026-01-32',
        'a thirteenth month' => '2026-13-01',
        'a zeroth month' => '2026-00-10',
        'a zeroth day' => '2026-05-00',
    ]);

    it('quotes the value it could not read', function () {
        expect(fn () => LocalDate::fromString('2026-02-30'))->toThrow(
            InvalidCustomerRegistrationPeriod::class,
            'The registration date [2026-02-30] is not a calendar date in the YYYY-MM-DD form.',
        );
    });

    it('refuses with a failure the responder can classify', function () {
        $refusal = null;

        try {
            LocalDate::fromString('2026-3-1');
        } catch (InvalidCustomerRegistrationPeriod $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_customer_registration_period')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    });
});

describe('comparing two dates', function () {
    it('knows a later date is after an earlier one', function () {
        expect(LocalDate::fromString('2026-03-02')->isAfter(LocalDate::fromString('2026-03-01')))->toBeTrue();
    });

    it('knows an earlier date is not after a later one', function () {
        expect(LocalDate::fromString('2026-03-01')->isAfter(LocalDate::fromString('2026-03-02')))->toBeFalse();
    });

    it('does not consider a date after itself', function () {
        expect(LocalDate::fromString('2026-03-01')->isAfter(LocalDate::fromString('2026-03-01')))->toBeFalse();
    });

    it('compares across a year boundary by calendar rather than by text', function () {
        expect(LocalDate::fromString('2027-01-01')->isAfter(LocalDate::fromString('2026-12-31')))->toBeTrue();
    });
});

describe('the day after', function () {
    it('moves to the next calendar day', function (string $day, string $next) {
        expect(LocalDate::fromString($day)->nextDay()->toString())->toBe($next);
    })->with([
        'within a month' => ['2026-03-10', '2026-03-11'],
        'across the end of a thirty-one day month' => ['2026-03-31', '2026-04-01'],
        'across the end of a thirty day month' => ['2026-04-30', '2026-05-01'],
        'across the end of February' => ['2026-02-28', '2026-03-01'],
        'into a leap day' => ['2028-02-28', '2028-02-29'],
        'out of a leap day' => ['2028-02-29', '2028-03-01'],
        'across the end of the year' => ['2026-12-31', '2027-01-01'],
        'out of a spring forward day' => ['2026-03-29', '2026-03-30'],
        'out of a fall back day' => ['2026-10-25', '2026-10-26'],
    ]);

    it('leaves the date it started from untouched', function () {
        $day = LocalDate::fromString('2026-03-31');

        $day->nextDay();

        expect($day->toString())->toBe('2026-03-31');
    });
});

describe('the instant the day starts in a zone', function () {
    it('answers local midnight as a UTC instant', function (string $day, string $zone, string $instant) {
        expect(LocalDate::fromString($day)->startsAtIn(new DateTimeZone($zone))->format(DATE_ATOM))->toBe($instant);
    })->with([
        'Madrid in winter' => ['2026-01-15', 'Europe/Madrid', '2026-01-14T23:00:00+00:00'],
        'Madrid in summer' => ['2026-07-15', 'Europe/Madrid', '2026-07-14T22:00:00+00:00'],
        'Madrid on the spring forward day' => ['2026-03-29', 'Europe/Madrid', '2026-03-28T23:00:00+00:00'],
        'Madrid the day after spring forward' => ['2026-03-30', 'Europe/Madrid', '2026-03-29T22:00:00+00:00'],
        'Madrid on the fall back day' => ['2026-10-25', 'Europe/Madrid', '2026-10-24T22:00:00+00:00'],
        'Madrid the day after fall back' => ['2026-10-26', 'Europe/Madrid', '2026-10-25T23:00:00+00:00'],
        'Mexico City, west of UTC' => ['2026-03-29', 'America/Mexico_City', '2026-03-29T06:00:00+00:00'],
        'UTC itself' => ['2026-03-29', 'UTC', '2026-03-29T00:00:00+00:00'],
    ]);

    it('hands the instant back in UTC', function () {
        expect(LocalDate::fromString('2026-03-29')->startsAtIn(new DateTimeZone('Europe/Madrid'))->getTimezone()->getName())
            ->toBe('UTC');
    });
});
