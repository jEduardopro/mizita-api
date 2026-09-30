<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\InvalidCustomerRegistrationPeriod;
use App\Domains\Customers\ValueObjects\LocalDate;
use App\Domains\Customers\ValueObjects\RegistrationPeriod;
use App\Domains\Customers\ValueObjects\RegistrationWindow;

beforeEach(function () {
    $this->madrid = new DateTimeZone('Europe/Madrid');

    $this->period = fn (string $from, string $to): RegistrationPeriod => RegistrationPeriod::between(
        LocalDate::fromString($from),
        LocalDate::fromString($to),
    );

    $this->hoursOf = fn (RegistrationWindow $window): int => intdiv(
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
        $period = ($this->period)('2026-03-29', '2026-03-29');

        expect($period->from->toString())->toBe('2026-03-29')
            ->and($period->to->toString())->toBe('2026-03-29');
    });

    it('refuses a period that ends before it starts', function () {
        expect(fn () => ($this->period)('2026-03-02', '2026-03-01'))->toThrow(
            InvalidCustomerRegistrationPeriod::class,
            'A registration period has to start on or before it ends, got [2026-03-02] to [2026-03-01].',
        );
    });

    it('refuses a period reversed across a year boundary', function () {
        expect(fn () => ($this->period)('2027-01-01', '2026-12-31'))
            ->toThrow(InvalidCustomerRegistrationPeriod::class);
    });
});

describe('the window a period covers in a zone', function () {
    it('covers an ordinary day from local midnight to the next local midnight', function () {
        $window = ($this->period)('2026-01-15', '2026-01-15')->windowIn($this->madrid);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-01-14T23:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-01-15T23:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(24);
    });

    it('covers only twenty-three hours on the spring forward day', function () {
        $window = ($this->period)('2026-03-29', '2026-03-29')->windowIn($this->madrid);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-03-28T23:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-03-29T22:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(23);
    });

    it('covers twenty-five hours on the fall back day', function () {
        $window = ($this->period)('2026-10-25', '2026-10-25')->windowIn($this->madrid);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-10-24T22:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-10-25T23:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(25);
    });

    it('converts each end of a multi-day range on its own date, across a spring forward', function () {
        $window = ($this->period)('2026-03-27', '2026-03-31')->windowIn($this->madrid);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-03-26T23:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-03-31T22:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(5 * 24 - 1);
    });

    it('converts each end of a multi-day range on its own date, across a fall back', function () {
        $window = ($this->period)('2026-10-01', '2026-10-31')->windowIn($this->madrid);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-09-30T22:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-10-31T23:00:00+00:00')
            ->and(($this->hoursOf)($window))->toBe(31 * 24 + 1);
    });

    it('covers a range that crosses the end of the year', function () {
        $window = ($this->period)('2026-12-31', '2027-01-01')->windowIn($this->madrid);

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-12-30T23:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2027-01-01T23:00:00+00:00');
    });

    it('reads the day in the zone it is given, not in UTC', function () {
        $window = ($this->period)('2026-03-29', '2026-03-29')->windowIn(new DateTimeZone('America/Mexico_City'));

        expect($window->startsAt->format(DATE_ATOM))->toBe('2026-03-29T06:00:00+00:00')
            ->and($window->endsAt->format(DATE_ATOM))->toBe('2026-03-30T06:00:00+00:00');
    });

    it('ends exactly where the next day starts, so adjacent periods neither overlap nor leave a gap', function (string $day, string $nextDay) {
        $first = ($this->period)($day, $day)->windowIn($this->madrid);
        $second = ($this->period)($nextDay, $nextDay)->windowIn($this->madrid);

        expect($first->endsAt)->toEqual($second->startsAt);
    })->with([
        'into a spring forward day' => ['2026-03-28', '2026-03-29'],
        'out of a spring forward day' => ['2026-03-29', '2026-03-30'],
        'into a fall back day' => ['2026-10-24', '2026-10-25'],
        'out of a fall back day' => ['2026-10-25', '2026-10-26'],
    ]);

    it('hands both ends back in UTC', function () {
        $window = ($this->period)('2026-10-25', '2026-10-25')->windowIn($this->madrid);

        expect($window->startsAt->getTimezone()->getName())->toBe('UTC')
            ->and($window->endsAt->getTimezone()->getName())->toBe('UTC');
    });
});
