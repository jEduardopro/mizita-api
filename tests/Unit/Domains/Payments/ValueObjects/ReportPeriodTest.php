<?php

declare(strict_types=1);

use App\Domains\Payments\Exceptions\InvalidPaymentReportPeriod;
use App\Domains\Payments\ValueObjects\LocalDate;
use App\Domains\Payments\ValueObjects\ReportPeriod;
use App\Domains\Payments\ValueObjects\ReportWindow;

beforeEach(function () {
    $this->newYork = new DateTimeZone('America/New_York');
    $this->madrid = new DateTimeZone('Europe/Madrid');

    $this->period = fn (string $from, string $to): ReportPeriod => ReportPeriod::between(
        LocalDate::fromString($from),
        LocalDate::fromString($to),
    );

    $this->hoursOf = fn (ReportWindow $window): int => intdiv(
        $window->endsAt->getTimestamp() - $window->startsAt->getTimestamp(),
        3600,
    );
});

describe('drawing a period', function () {
    it('keeps the two dates it was drawn between', function () {
        $period = ($this->period)('2026-03-01', '2026-03-31');

        expect($period->from->toString())->toBe('2026-03-01')
            ->and($period->to->toString())->toBe('2026-03-31');
    });

    it('accepts a period of a single day', function () {
        $period = ($this->period)('2026-03-08', '2026-03-08');

        expect($period->from->toString())->toBe('2026-03-08')
            ->and($period->to->toString())->toBe('2026-03-08');
    });

    it('refuses a period that ends before it starts, quoting both dates', function () {
        expect(fn () => ($this->period)('2026-03-02', '2026-03-01'))->toThrow(
            InvalidPaymentReportPeriod::class,
            'A report period has to start on or before it ends, got [2026-03-02] to [2026-03-01].',
        );
    });

    it('refuses a period reversed across a year boundary', function () {
        expect(fn () => ($this->period)('2027-01-01', '2026-12-31'))
            ->toThrow(InvalidPaymentReportPeriod::class);
    });
});

describe('the window a period covers in a zone', function () {
    it('covers an ordinary day from local midnight to the next local midnight', function () {
        $window = ($this->period)('2026-01-15', '2026-01-15')->windowIn($this->newYork);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-01-15T05:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-01-16T05:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(24);
    });

    it('covers only twenty-three hours on the New York spring forward day', function () {
        $window = ($this->period)('2026-03-08', '2026-03-08')->windowIn($this->newYork);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-03-08T05:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-03-09T04:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(23);
    });

    it('covers twenty-five hours on the New York fall back day', function () {
        $window = ($this->period)('2026-11-01', '2026-11-01')->windowIn($this->newYork);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-11-01T04:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-11-02T05:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(25);
    });

    it('covers only twenty-three hours on the Madrid spring forward day', function () {
        $window = ($this->period)('2026-03-29', '2026-03-29')->windowIn($this->madrid);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-03-28T23:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-03-29T22:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(23);
    });

    it('covers twenty-five hours on the Madrid fall back day', function () {
        $window = ($this->period)('2026-10-25', '2026-10-25')->windowIn($this->madrid);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-10-24T22:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-10-25T23:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(25);
    });

    it('converts each end of a month on its own date, across the New York spring forward', function () {
        $window = ($this->period)('2026-03-01', '2026-03-31')->windowIn($this->newYork);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-03-01T05:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-04-01T04:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(31 * 24 - 1);
    });

    it('converts each end of a month on its own date, across the New York fall back', function () {
        $window = ($this->period)('2026-11-01', '2026-11-30')->windowIn($this->newYork);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-11-01T04:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-12-01T05:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(30 * 24 + 1);
    });

    it('covers only twenty-three hours on a day whose local midnight never happens', function () {
        $window = ($this->period)('2026-09-06', '2026-09-06')->windowIn(new DateTimeZone('America/Santiago'));

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-09-06T04:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-09-07T03:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(23);
    });

    it('covers a range that crosses the end of the year', function () {
        $window = ($this->period)('2026-12-31', '2027-01-01')->windowIn($this->newYork);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-12-31T05:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2027-01-02T05:00:00+00:00');
    });

    it('reads the day in the zone it is given, not in UTC', function () {
        $newYork = ($this->period)('2026-03-08', '2026-03-08')->windowIn($this->newYork);
        $utc = ($this->period)('2026-03-08', '2026-03-08')->windowIn(new DateTimeZone('UTC'));

        expect($utc->startsAt->format(DATE_ATOM))->toBe('2026-03-08T00:00:00+00:00')
            ->and($utc->endsAt->format(DATE_ATOM))->toBe('2026-03-09T00:00:00+00:00')
            ->and($newYork->startsAt)->not->toEqual($utc->startsAt);
    });

    it('ends exactly where the next day starts, so adjacent periods neither overlap nor leave a gap', function (string $zone, string $day, string $nextDay) {
        $timezone = new DateTimeZone($zone);
        $first = ($this->period)($day, $day)->windowIn($timezone);
        $second = ($this->period)($nextDay, $nextDay)->windowIn($timezone);

        expect($first->endsAt)->toEqual($second->startsAt);
    })->with([
        'into the New York spring forward day' => ['America/New_York', '2026-03-07', '2026-03-08'],
        'out of the New York spring forward day' => ['America/New_York', '2026-03-08', '2026-03-09'],
        'into the New York fall back day' => ['America/New_York', '2026-10-31', '2026-11-01'],
        'out of the New York fall back day' => ['America/New_York', '2026-11-01', '2026-11-02'],
        'into the Madrid spring forward day' => ['Europe/Madrid', '2026-03-28', '2026-03-29'],
        'out of the Madrid fall back day' => ['Europe/Madrid', '2026-10-25', '2026-10-26'],
        'into a day whose midnight never happens' => ['America/Santiago', '2026-09-05', '2026-09-06'],
    ]);

    it('hands both ends back in UTC', function () {
        $window = ($this->period)('2026-11-01', '2026-11-01')->windowIn($this->newYork);

        expect($window->startsAt->getTimezone()->getName())->toBe('UTC')
            ->and($window->endsAt->getTimezone()->getName())->toBe('UTC');
    });
});
