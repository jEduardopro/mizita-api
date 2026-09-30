<?php

declare(strict_types=1);

use App\Domains\Statistics\Exceptions\InvalidStatisticsPeriod;
use App\Domains\Statistics\ValueObjects\LocalDate;
use App\Shared\Contracts\DomainFailure;

describe('reading a date', function () {
    it('keeps a calendar date written as YYYY-MM-DD', function (string $date) {
        expect(LocalDate::fromString($date)->toString())->toBe($date);
    })->with([
        'a day in september' => '2026-09-29',
        'the first of the year' => '2026-01-01',
        'the last of the year' => '2026-12-31',
        'a leap day' => '2028-02-29',
    ]);

    it('rejects anything that is not a calendar date', function (string $date) {
        expect(fn () => LocalDate::fromString($date))->toThrow(InvalidStatisticsPeriod::class);
    })->with('malformed statistics dates');

    it('rejects a malformed date with a failure the transport can classify', function (string $date) {
        try {
            LocalDate::fromString($date);
        } catch (InvalidStatisticsPeriod $failure) {
            expect($failure)->toBeInstanceOf(DomainFailure::class)
                ->and($failure->errorCode())->toBe('invalid_statistics_period');

            return;
        }

        $this->fail("fromString() accepted [{$date}].");
    })->with('malformed statistics dates');
});

describe('the local date of an instant', function () {
    it('reads the date on the business wall clock, not in UTC', function () {
        $lateEveningInMonterrey = new DateTimeImmutable('2026-09-30T05:30:00+00:00');

        expect(LocalDate::at($lateEveningInMonterrey, new DateTimeZone('America/Monterrey'))->toString())
            ->toBe('2026-09-29');
    });

    it('turns the page at local midnight', function () {
        $localMidnight = new DateTimeImmutable('2026-09-30T06:00:00+00:00');

        expect(LocalDate::at($localMidnight, new DateTimeZone('America/Monterrey'))->toString())
            ->toBe('2026-09-30');
    });
});

describe('the same day one month earlier', function () {
    it('keeps the day of the month when the previous month has it', function (string $date, string $expected) {
        expect(LocalDate::fromString($date)->sameDayOfPreviousMonth()->toString())->toBe($expected);
    })->with([
        'late september' => ['2026-09-29', '2026-08-29'],
        'the first of march' => ['2026-03-01', '2026-02-01'],
        'january into december of the year before' => ['2026-01-15', '2025-12-15'],
        'the first of january' => ['2026-01-01', '2025-12-01'],
    ]);

    it('clamps to the last day of a shorter previous month', function (string $date, string $expected) {
        expect(LocalDate::fromString($date)->sameDayOfPreviousMonth()->toString())->toBe($expected);
    })->with([
        'the 31st of march in a common year' => ['2026-03-31', '2026-02-28'],
        'the 29th of march in a common year' => ['2026-03-29', '2026-02-28'],
        'the 31st of march in a leap year' => ['2028-03-31', '2028-02-29'],
        'the 30th of march in a leap year' => ['2028-03-30', '2028-02-29'],
        'the 31st of may' => ['2026-05-31', '2026-04-30'],
        'the 31st of october' => ['2026-10-31', '2026-09-30'],
    ]);
});

describe('counting days', function () {
    it('counts both ends of the range', function (string $from, string $to, int $days) {
        expect(LocalDate::fromString($from)->daysThrough(LocalDate::fromString($to)))->toBe($days);
    })->with([
        'a single day' => ['2026-09-29', '2026-09-29', 1],
        'a week' => ['2026-09-23', '2026-09-29', 7],
        'february in a common year' => ['2026-02-01', '2026-02-28', 28],
        'february in a leap year' => ['2028-02-01', '2028-02-29', 29],
        'a common year' => ['2026-01-01', '2026-12-31', 365],
    ]);

    it('steps across a month and a year boundary', function () {
        expect(LocalDate::fromString('2026-12-31')->plusDays(1)->toString())->toBe('2027-01-01')
            ->and(LocalDate::fromString('2026-03-01')->minusDays(1)->toString())->toBe('2026-02-28');
    });
});

describe('the instant a local date starts', function () {
    it('is local midnight expressed in UTC', function () {
        $startsAt = LocalDate::fromString('2026-09-01')->startsAtIn(new DateTimeZone('America/Monterrey'));

        expect($startsAt->format(DATE_ATOM))->toBe('2026-09-01T06:00:00+00:00')
            ->and($startsAt->getTimezone()->getName())->toBe('UTC');
    });

    it('uses the offset of that very date, not a constant one', function () {
        $madrid = new DateTimeZone('Europe/Madrid');

        expect(LocalDate::fromString('2026-10-25')->startsAtIn($madrid)->format(DATE_ATOM))
            ->toBe('2026-10-24T22:00:00+00:00')
            ->and(LocalDate::fromString('2026-10-26')->startsAtIn($madrid)->format(DATE_ATOM))
            ->toBe('2026-10-25T23:00:00+00:00');
    });
});

dataset('malformed statistics dates', [
    'empty' => '',
    'whitespace only' => '   ',
    'a word' => 'today',
    'slashes' => '2026/09/01',
    'day first' => '01-09-2026',
    'a two digit year' => '26-09-01',
    'unpadded month and day' => '2026-9-1',
    'a timestamp' => '2026-09-01T00:00:00',
    'a thirteenth month' => '2026-13-01',
    'a month zero' => '2026-00-10',
    'a day zero' => '2026-09-00',
    'the 31st of september' => '2026-09-31',
    'a leap day in a common year' => '2026-02-29',
    'full width digits' => '２０２６-09-01',
    'a trailing letter' => '2026-09-01x',
]);
