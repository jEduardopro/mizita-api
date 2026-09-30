<?php

declare(strict_types=1);

use App\Domains\Statistics\Exceptions\InvalidStatisticsPeriod;
use App\Domains\Statistics\Exceptions\StatisticsPeriodTooWide;
use App\Domains\Statistics\ValueObjects\LocalDate;
use App\Domains\Statistics\ValueObjects\StatisticsPeriod;
use Tests\Support\Statistics\WindowSpan;

describe('a period between two dates', function () {
    it('keeps both dates', function () {
        $period = StatisticsPeriod::between(LocalDate::fromString('2026-09-01'), LocalDate::fromString('2026-09-29'));

        expect($period->from->toString())->toBe('2026-09-01')
            ->and($period->to->toString())->toBe('2026-09-29');
    });

    it('accepts a period of a single day', function () {
        $day = LocalDate::fromString('2026-09-29');

        expect(StatisticsPeriod::between($day, $day)->windowIn(new DateTimeZone('UTC'))->dates())->toHaveCount(1);
    });

    it('rejects a period that ends before it starts', function () {
        expect(fn () => StatisticsPeriod::between(LocalDate::fromString('2026-09-02'), LocalDate::fromString('2026-09-01')))
            ->toThrow(InvalidStatisticsPeriod::class);
    });

    it('accepts a period of exactly the maximum number of days', function (string $from, string $to) {
        $period = StatisticsPeriod::between(LocalDate::fromString($from), LocalDate::fromString($to));

        expect($period->from->daysThrough($period->to))->toBe(366);
    })->with([
        'a common year and one day' => ['2025-01-01', '2026-01-01'],
        'a year holding a leap day' => ['2027-03-01', '2028-02-29'],
    ]);

    it('rejects a period one day wider than the maximum', function (string $from, string $to) {
        expect(fn () => StatisticsPeriod::between(LocalDate::fromString($from), LocalDate::fromString($to)))
            ->toThrow(StatisticsPeriodTooWide::class);
    })->with([
        'a common year and two days' => ['2025-01-01', '2026-01-02'],
        'a year holding a leap day and one more day' => ['2027-03-01', '2028-03-01'],
    ]);
});

describe('the periods the calendar derives', function () {
    it('runs month to date from the first of the month', function () {
        $period = StatisticsPeriod::monthToDate(LocalDate::fromString('2026-09-29'));

        expect($period->from->toString())->toBe('2026-09-01')
            ->and($period->to->toString())->toBe('2026-09-29');
    });

    it('covers a single day on the first of the month', function () {
        $period = StatisticsPeriod::monthToDate(LocalDate::fromString('2026-09-01'));

        expect($period->from->toString())->toBe('2026-09-01')
            ->and($period->to->toString())->toBe('2026-09-01');
    });

    it('ends a trailing run on the given day and counts it in', function () {
        $dates = StatisticsPeriod::trailingDaysEndingOn(LocalDate::fromString('2026-03-03'), 7)
            ->windowIn(new DateTimeZone('UTC'))
            ->dates();

        expect(array_map(static fn (LocalDate $date): string => $date->toString(), $dates))->toBe([
            '2026-02-25', '2026-02-26', '2026-02-27', '2026-02-28', '2026-03-01', '2026-03-02', '2026-03-03',
        ]);
    });

    it('moves both ends back one month, clamping each to its own month', function (string $from, string $to, string $previousFrom, string $previousTo) {
        $previous = StatisticsPeriod::between(LocalDate::fromString($from), LocalDate::fromString($to))->previousMonth();

        expect($previous->from->toString())->toBe($previousFrom)
            ->and($previous->to->toString())->toBe($previousTo);
    })->with([
        'the first 29 days of september' => ['2026-09-01', '2026-09-29', '2026-08-01', '2026-08-29'],
        'the whole of march in a common year' => ['2026-03-01', '2026-03-31', '2026-02-01', '2026-02-28'],
        'the whole of march in a leap year' => ['2028-03-01', '2028-03-31', '2028-02-01', '2028-02-29'],
        'the tail of march' => ['2026-03-29', '2026-03-31', '2026-02-28', '2026-02-28'],
        'a range across new year' => ['2025-12-15', '2026-01-15', '2025-11-15', '2025-12-15'],
    ]);
});

describe('the instants a period covers', function () {
    it('starts at local midnight of its first day and ends at local midnight after its last', function () {
        $window = StatisticsPeriod::between(LocalDate::fromString('2026-09-01'), LocalDate::fromString('2026-09-29'))
            ->windowIn(new DateTimeZone('America/Monterrey'));

        expect(WindowSpan::of($window))->toBe([
            'from' => '2026-09-01',
            'to' => '2026-09-29',
            'startsAt' => '2026-09-01T06:00:00+00:00',
            'endsAt' => '2026-09-30T06:00:00+00:00',
        ]);
    });

    it('lasts 25 hours on the day Madrid falls back', function () {
        $window = StatisticsPeriod::singleDay(LocalDate::fromString('2026-10-25'))->windowIn(new DateTimeZone('Europe/Madrid'));

        expect(WindowSpan::hoursIn($window))->toBe(25);
    });

    it('lasts 23 hours on the day Madrid springs forward', function () {
        $window = StatisticsPeriod::singleDay(LocalDate::fromString('2026-03-29'))->windowIn(new DateTimeZone('Europe/Madrid'));

        expect(WindowSpan::hoursIn($window))->toBe(23);
    });
});
