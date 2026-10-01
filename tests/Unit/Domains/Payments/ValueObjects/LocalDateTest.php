<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use App\Domains\Payments\ValueObjects\LocalDate;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

describe('reading a calendar date', function () {
    it('reads back the calendar date it was given', function (string $raw) {
        expect(LocalDate::fromString($raw)->toString())->toBe($raw);
    })->with([
        'an ordinary day' => '2026-03-01',
        'the last day of the year' => '2026-12-31',
        'a leap day' => '2028-02-29',
        'the New York spring forward day' => '2026-03-08',
        'the New York fall back day' => '2026-11-01',
        'the Madrid spring forward day' => '2026-03-29',
        'the Madrid fall back day' => '2026-10-25',
    ]);

    it('tolerates whitespace around the date', function (string $raw) {
        expect(LocalDate::fromString($raw)->toString())->toBe('2026-03-01');
    })->with(['spaces' => '  2026-03-01  ', 'tab' => "\t2026-03-01", 'newline' => "2026-03-01\n"]);
});

describe('refusing what is not a calendar date', function () {
    it('refuses anything that is not the YYYY-MM-DD form', function (string $raw) {
        expect(fn () => LocalDate::fromString($raw))
            ->toThrow(InvalidPaymentReportPeriod::class, 'is not a calendar date in the YYYY-MM-DD form');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'no padding on the month and day' => '2026-3-1',
        'no padding on the day' => '2026-03-1',
        'day first' => '01/03/2026',
        'slashes' => '2026/03/01',
        'a two digit year' => '26-03-01',
        'no separators' => '20260301',
        'only a year and a month' => '2026-03',
        'a timestamp' => '2026-03-01T00:00:00',
        'a date with a time' => '2026-03-01 10:00',
        'a date with an offset' => '2026-03-01+02:00',
        'a signed year' => '+2026-03-01',
        'a five digit year' => '99999-01-01',
        'a word' => 'yesterday',
        'accented text' => 'mañana',
        'sql injection' => "2026-03-01'; drop table payments",
    ]);

    it('refuses a day the calendar does not have, rather than rolling it over', function (string $raw) {
        expect(fn () => LocalDate::fromString($raw))->toThrow(InvalidPaymentReportPeriod::class);
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
            InvalidPaymentReportPeriod::class,
            'The report date [2026-02-30] is not a calendar date in the YYYY-MM-DD form.',
        );
    });

    it('refuses with a failure the responder can classify', function () {
        $refusal = null;

        try {
            LocalDate::fromString('2026-3-1');
        } catch (InvalidPaymentReportPeriod $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_payment_report_period')
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
        'out of the New York spring forward day' => ['2026-03-08', '2026-03-09'],
        'out of the New York fall back day' => ['2026-11-01', '2026-11-02'],
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
        'New York in winter' => ['2026-01-15', 'America/New_York', '2026-01-15T05:00:00+00:00'],
        'New York on the spring forward day' => ['2026-03-08', 'America/New_York', '2026-03-08T05:00:00+00:00'],
        'New York the day after spring forward' => ['2026-03-09', 'America/New_York', '2026-03-09T04:00:00+00:00'],
        'New York on the fall back day' => ['2026-11-01', 'America/New_York', '2026-11-01T04:00:00+00:00'],
        'New York the day after fall back' => ['2026-11-02', 'America/New_York', '2026-11-02T05:00:00+00:00'],
        'Madrid on the spring forward day' => ['2026-03-29', 'Europe/Madrid', '2026-03-28T23:00:00+00:00'],
        'Madrid the day after spring forward' => ['2026-03-30', 'Europe/Madrid', '2026-03-29T22:00:00+00:00'],
        'Madrid on the fall back day' => ['2026-10-25', 'Europe/Madrid', '2026-10-24T22:00:00+00:00'],
        'Madrid the day after fall back' => ['2026-10-26', 'Europe/Madrid', '2026-10-25T23:00:00+00:00'],
        'UTC itself' => ['2026-03-08', 'UTC', '2026-03-08T00:00:00+00:00'],
    ]);

    it('starts a day whose local midnight never happens at the first instant it has', function () {
        expect(LocalDate::fromString('2026-09-06')->startsAtIn(new DateTimeZone('America/Santiago'))->format(DATE_ATOM))
            ->toBe('2026-09-06T04:00:00+00:00');
    });

    it('hands the instant back in UTC', function () {
        expect(LocalDate::fromString('2026-03-08')->startsAtIn(new DateTimeZone('America/New_York'))->getTimezone()->getName())
            ->toBe('UTC');
    });
});
