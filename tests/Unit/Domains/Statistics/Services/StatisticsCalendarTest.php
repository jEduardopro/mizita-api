<?php

declare(strict_types=1);

use App\Domains\Statistics\Exceptions\InvalidStatisticsPeriod;
use App\Domains\Statistics\Services\StatisticsCalendar;
use App\Domains\Statistics\ValueObjects\LocalDate;
use App\Domains\Statistics\ValueObjects\StatisticsPeriod;
use Tests\Support\Statistics\WindowSpan;

beforeEach(function () {
    $this->calendar = new StatisticsCalendar;
    $this->monterrey = new DateTimeZone('America/Monterrey');
    $this->noonInMonterrey = new DateTimeImmutable('2026-09-29T18:00:00+00:00');
    $this->period = fn (string $from, string $to): StatisticsPeriod => StatisticsPeriod::between(
        LocalDate::fromString($from),
        LocalDate::fromString($to),
    );
});

describe('with no period requested', function () {
    it('reports from the first of the month through today', function () {
        $windows = $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, null);

        expect(WindowSpan::of($windows->current))->toBe([
            'from' => '2026-09-01',
            'to' => '2026-09-29',
            'startsAt' => '2026-09-01T06:00:00+00:00',
            'endsAt' => '2026-09-30T06:00:00+00:00',
        ]);
    });

    it('compares against the same dates of the previous month', function () {
        $windows = $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, null);

        expect(WindowSpan::of($windows->previous))->toBe([
            'from' => '2026-08-01',
            'to' => '2026-08-29',
            'startsAt' => '2026-08-01T06:00:00+00:00',
            'endsAt' => '2026-08-30T06:00:00+00:00',
        ]);
    });

    it('clamps the comparison to the end of a shorter previous month', function () {
        $windows = $this->calendar->windowsFor(
            new DateTimeImmutable('2026-03-31T18:00:00+00:00'),
            $this->monterrey,
            null,
        );

        expect($windows->current->from->toString())->toBe('2026-03-01')
            ->and($windows->current->to->toString())->toBe('2026-03-31')
            ->and($windows->previous->from->toString())->toBe('2026-02-01')
            ->and($windows->previous->to->toString())->toBe('2026-02-28');
    });

    it('covers a single day on the first of the month', function () {
        $windows = $this->calendar->windowsFor(
            new DateTimeImmutable('2026-09-01T18:00:00+00:00'),
            $this->monterrey,
            null,
        );

        expect($windows->current->from->toString())->toBe('2026-09-01')
            ->and($windows->current->to->toString())->toBe('2026-09-01')
            ->and($windows->previous->from->toString())->toBe('2026-08-01')
            ->and($windows->previous->to->toString())->toBe('2026-08-01');
    });

    it('stays in the local month while UTC has already moved to the next one', function () {
        $lateOnTheLastEveningOfSeptember = new DateTimeImmutable('2026-10-01T05:00:00+00:00');

        $windows = $this->calendar->windowsFor($lateOnTheLastEveningOfSeptember, $this->monterrey, null);

        expect($windows->current->from->toString())->toBe('2026-09-01')
            ->and($windows->current->to->toString())->toBe('2026-09-30')
            ->and($windows->today->from->toString())->toBe('2026-09-30');
    });
});

describe('with a period requested', function () {
    it('reports exactly the dates asked for', function () {
        $windows = $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, ($this->period)('2026-07-01', '2026-07-31'));

        expect(WindowSpan::of($windows->current))->toBe([
            'from' => '2026-07-01',
            'to' => '2026-07-31',
            'startsAt' => '2026-07-01T06:00:00+00:00',
            'endsAt' => '2026-08-01T06:00:00+00:00',
        ]);
    });

    it('compares a whole month against the whole previous month', function () {
        $windows = $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, ($this->period)('2026-07-01', '2026-07-31'));

        expect($windows->previous->from->toString())->toBe('2026-06-01')
            ->and($windows->previous->to->toString())->toBe('2026-06-30');
    });

    it('clamps the comparison of a month ending on the 31st to the end of february', function (string $from, string $to, string $previousTo) {
        $windows = $this->calendar->windowsFor(
            new DateTimeImmutable('2028-12-01T18:00:00+00:00'),
            $this->monterrey,
            ($this->period)($from, $to),
        );

        expect($windows->previous->to->toString())->toBe($previousTo);
    })->with([
        'a common year' => ['2026-03-01', '2026-03-31', '2026-02-28'],
        'a leap year' => ['2028-03-01', '2028-03-31', '2028-02-29'],
    ]);

    it('accepts a period ending today', function () {
        $windows = $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, ($this->period)('2026-09-10', '2026-09-29'));

        expect($windows->current->to->toString())->toBe('2026-09-29');
    });

    it('rejects a period ending after today', function () {
        expect(fn () => $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, ($this->period)('2026-09-10', '2026-09-30')))
            ->toThrow(InvalidStatisticsPeriod::class);
    });

    it('judges today on the business wall clock, not in UTC', function () {
        $eveningInMonterreyAlreadyTomorrowInUtc = new DateTimeImmutable('2026-09-30T03:00:00+00:00');

        expect(fn () => $this->calendar->windowsFor(
            $eveningInMonterreyAlreadyTomorrowInUtc,
            $this->monterrey,
            ($this->period)('2026-09-01', '2026-09-30'),
        ))->toThrow(InvalidStatisticsPeriod::class);
    });

    it('accepts the widest period allowed, ending today', function () {
        $windows = $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, ($this->period)('2025-09-29', '2026-09-29'));

        expect($windows->current->from->daysThrough($windows->current->to))->toBe(366);
    });
});

describe('today', function () {
    it('covers the local day of now, midnight to midnight', function () {
        $windows = $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, null);

        expect(WindowSpan::of($windows->today))->toBe([
            'from' => '2026-09-29',
            'to' => '2026-09-29',
            'startsAt' => '2026-09-29T06:00:00+00:00',
            'endsAt' => '2026-09-30T06:00:00+00:00',
        ]);
    });

    it('is still the local day when UTC has already reached the next one', function () {
        $halfAnHourBeforeLocalMidnight = new DateTimeImmutable('2026-09-30T05:30:00+00:00');

        $windows = $this->calendar->windowsFor($halfAnHourBeforeLocalMidnight, $this->monterrey, null);

        expect(WindowSpan::of($windows->today))->toBe([
            'from' => '2026-09-29',
            'to' => '2026-09-29',
            'startsAt' => '2026-09-29T06:00:00+00:00',
            'endsAt' => '2026-09-30T06:00:00+00:00',
        ]);
    });

    it('turns over at local midnight', function () {
        $localMidnight = new DateTimeImmutable('2026-09-30T06:00:00+00:00');

        expect($this->calendar->windowsFor($localMidnight, $this->monterrey, null)->today->from->toString())
            ->toBe('2026-09-30');
    });

    it('ignores the requested period', function () {
        $windows = $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, ($this->period)('2026-07-01', '2026-07-31'));

        expect($windows->today->from->toString())->toBe('2026-09-29');
    });
});

describe('the last seven days', function () {
    it('spans exactly seven local dates ending today', function () {
        $windows = $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, null);

        expect(array_map(static fn (LocalDate $date): string => $date->toString(), $windows->lastSevenDays->dates()))->toBe([
            '2026-09-23', '2026-09-24', '2026-09-25', '2026-09-26', '2026-09-27', '2026-09-28', '2026-09-29',
        ]);
    });

    it('covers the instants from the first local midnight to the one after today', function () {
        $windows = $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, null);

        expect(WindowSpan::of($windows->lastSevenDays))->toBe([
            'from' => '2026-09-23',
            'to' => '2026-09-29',
            'startsAt' => '2026-09-23T06:00:00+00:00',
            'endsAt' => '2026-09-30T06:00:00+00:00',
        ]);
    });

    it('reaches back across a year boundary', function () {
        $windows = $this->calendar->windowsFor(new DateTimeImmutable('2026-01-02T18:00:00+00:00'), $this->monterrey, null);

        expect($windows->lastSevenDays->from->toString())->toBe('2025-12-27')
            ->and($windows->lastSevenDays->to->toString())->toBe('2026-01-02');
    });

    it('ignores the requested period', function () {
        $windows = $this->calendar->windowsFor($this->noonInMonterrey, $this->monterrey, ($this->period)('2026-07-01', '2026-07-31'));

        expect($windows->lastSevenDays->from->toString())->toBe('2026-09-23')
            ->and($windows->lastSevenDays->to->toString())->toBe('2026-09-29');
    });
});

describe('across a daylight saving change', function () {
    beforeEach(function () {
        $this->madrid = new DateTimeZone('Europe/Madrid');
    });

    it('converts every date on its own offset in a week holding the fall back', function () {
        $mondayAfterTheLastSundayOfOctober = new DateTimeImmutable('2026-10-26T11:00:00+00:00');

        $windows = $this->calendar->windowsFor($mondayAfterTheLastSundayOfOctober, $this->madrid, null);

        expect(WindowSpan::of($windows->lastSevenDays))->toBe([
            'from' => '2026-10-20',
            'to' => '2026-10-26',
            'startsAt' => '2026-10-19T22:00:00+00:00',
            'endsAt' => '2026-10-26T23:00:00+00:00',
        ])->and(WindowSpan::hoursIn($windows->lastSevenDays))->toBe(7 * 24 + 1);
    });

    it('gives the fall back sunday 25 hours', function () {
        $windows = $this->calendar->windowsFor(
            new DateTimeImmutable('2026-10-25T20:00:00+00:00'),
            $this->madrid,
            null,
        );

        expect(WindowSpan::of($windows->today))->toBe([
            'from' => '2026-10-25',
            'to' => '2026-10-25',
            'startsAt' => '2026-10-24T22:00:00+00:00',
            'endsAt' => '2026-10-25T23:00:00+00:00',
        ])->and(WindowSpan::hoursIn($windows->today))->toBe(25);
    });

    it('gives the spring forward sunday 23 hours', function () {
        $windows = $this->calendar->windowsFor(
            new DateTimeImmutable('2026-03-29T20:00:00+00:00'),
            $this->madrid,
            null,
        );

        expect(WindowSpan::of($windows->today))->toBe([
            'from' => '2026-03-29',
            'to' => '2026-03-29',
            'startsAt' => '2026-03-28T23:00:00+00:00',
            'endsAt' => '2026-03-29T22:00:00+00:00',
        ])->and(WindowSpan::hoursIn($windows->today))->toBe(23);
    });

    it('compares a month holding the fall back against one on a single offset', function () {
        $windows = $this->calendar->windowsFor(
            new DateTimeImmutable('2026-11-15T12:00:00+00:00'),
            $this->madrid,
            ($this->period)('2026-10-01', '2026-10-31'),
        );

        expect(WindowSpan::of($windows->current))->toBe([
            'from' => '2026-10-01',
            'to' => '2026-10-31',
            'startsAt' => '2026-09-30T22:00:00+00:00',
            'endsAt' => '2026-10-31T23:00:00+00:00',
        ])->and(WindowSpan::of($windows->previous))->toBe([
            'from' => '2026-09-01',
            'to' => '2026-09-30',
            'startsAt' => '2026-08-31T22:00:00+00:00',
            'endsAt' => '2026-09-30T22:00:00+00:00',
        ]);
    });

    it('starts a day at the first instant that exists when the clock skips midnight itself', function () {
        $windows = $this->calendar->windowsFor(
            new DateTimeImmutable('2026-09-06T15:00:00+00:00'),
            new DateTimeZone('America/Santiago'),
            null,
        );

        expect(WindowSpan::of($windows->today))->toBe([
            'from' => '2026-09-06',
            'to' => '2026-09-06',
            'startsAt' => '2026-09-06T04:00:00+00:00',
            'endsAt' => '2026-09-07T03:00:00+00:00',
        ])->and(WindowSpan::hoursIn($windows->today))->toBe(23);
    });

    it('keeps 24 hour days in Monterrey, which no longer observes daylight saving', function () {
        $windows = $this->calendar->windowsFor(
            new DateTimeImmutable('2026-04-05T18:00:00+00:00'),
            $this->monterrey,
            null,
        );

        expect(WindowSpan::of($windows->today))->toBe([
            'from' => '2026-04-05',
            'to' => '2026-04-05',
            'startsAt' => '2026-04-05T06:00:00+00:00',
            'endsAt' => '2026-04-06T06:00:00+00:00',
        ]);
    });
});
