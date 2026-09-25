<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\InvalidExternalCalendarEvent;
use App\Domains\Integrations\ValueObjects\AllDayEventSpan;
use App\Domains\Integrations\ValueObjects\EventSpan;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

beforeEach(function () {
    $this->madrid = new DateTimeZone('Europe/Madrid');
});

describe('turning an all-day event into busy time', function () {
    it('is a span an external event can carry', function () {
        expect(new AllDayEventSpan('2026-03-10', '2026-03-11'))->toBeInstanceOf(EventSpan::class);
    });

    it('covers an ordinary day from local midnight to local midnight', function () {
        $interval = (new AllDayEventSpan('2026-03-10', '2026-03-11'))->intervalIn($this->madrid);

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-09T23:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-10T23:00:00+00:00');
    });

    it('covers a summer day at the summer offset', function () {
        $interval = (new AllDayEventSpan('2026-07-15', '2026-07-16'))->intervalIn($this->madrid);

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-07-14T22:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-07-15T22:00:00+00:00');
    });

    it('hands the busy time back in UTC', function () {
        $interval = (new AllDayEventSpan('2026-07-15', '2026-07-16'))->intervalIn($this->madrid);

        expect($interval->startsAt->getTimezone()->getName())->toBe('UTC')
            ->and($interval->endsAt->getTimezone()->getName())->toBe('UTC');
    });

    it('covers every day of an event lasting several days', function () {
        $interval = (new AllDayEventSpan('2026-03-10', '2026-03-13'))->intervalIn($this->madrid);

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-09T23:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-12T23:00:00+00:00');
    });

    it('reads the same dates as a different stretch of time in another zone', function () {
        $interval = (new AllDayEventSpan('2026-03-10', '2026-03-11'))->intervalIn(new DateTimeZone('America/Mexico_City'));

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-10T06:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-11T06:00:00+00:00');
    });
});

describe('an all-day event across a DST change in Europe/Madrid', function () {
    it('covers the 23 hours of the spring-forward day', function () {
        $interval = (new AllDayEventSpan('2026-03-29', '2026-03-30'))->intervalIn($this->madrid);

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-28T23:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-29T22:00:00+00:00')
            ->and($interval->endsAt->getTimestamp() - $interval->startsAt->getTimestamp())->toBe(23 * 3600);
    });

    it('covers the 25 hours of the fall-back day', function () {
        $interval = (new AllDayEventSpan('2026-10-25', '2026-10-26'))->intervalIn($this->madrid);

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-10-24T22:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-10-25T23:00:00+00:00')
            ->and($interval->endsAt->getTimestamp() - $interval->startsAt->getTimestamp())->toBe(25 * 3600);
    });

    it('converts each end on its own date when the event straddles the spring-forward day', function () {
        $interval = (new AllDayEventSpan('2026-03-28', '2026-03-31'))->intervalIn($this->madrid);

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-27T23:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-30T22:00:00+00:00')
            ->and($interval->endsAt->getTimestamp() - $interval->startsAt->getTimestamp())->toBe(71 * 3600);
    });

    it('converts each end on its own date when the event straddles the fall-back day', function () {
        $interval = (new AllDayEventSpan('2026-10-24', '2026-10-27'))->intervalIn($this->madrid);

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-10-23T22:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-10-26T23:00:00+00:00')
            ->and($interval->endsAt->getTimestamp() - $interval->startsAt->getTimestamp())->toBe(73 * 3600);
    });
});

describe('an all-day event in a zone whose clocks skip midnight', function () {
    it('starts at the first local instant of a day that has no midnight', function () {
        $interval = (new AllDayEventSpan('2026-09-06', '2026-09-07'))->intervalIn(new DateTimeZone('America/Santiago'));

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-09-06T04:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-09-07T03:00:00+00:00');
    });

    it('ends where the next day begins when that next day has no midnight', function () {
        $interval = (new AllDayEventSpan('2026-09-05', '2026-09-06'))->intervalIn(new DateTimeZone('America/Santiago'));

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-09-05T04:00:00+00:00')
            ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-09-06T04:00:00+00:00');
    });
});

describe('refusing a span that is not an all-day event', function () {
    it('refuses a date that is not a calendar date', function (string $firstDate, string $dayAfterLastDate, string $offending) {
        expect(fn () => new AllDayEventSpan($firstDate, $dayAfterLastDate))
            ->toThrow(InvalidExternalCalendarEvent::class, "The external calendar event date [{$offending}] is not a calendar date.");
    })->with([
        'an empty first date' => ['', '2026-03-11', ''],
        'an empty last date' => ['2026-03-10', '', ''],
        'a day february does not have' => ['2026-02-30', '2026-03-02', '2026-02-30'],
        'a month that does not exist' => ['2026-13-01', '2027-01-02', '2026-13-01'],
        'an unpadded month' => ['2026-3-10', '2026-03-11', '2026-3-10'],
        'a date carrying a time' => ['2026-03-10T00:00:00', '2026-03-11', '2026-03-10T00:00:00'],
        'surrounding whitespace' => [' 2026-03-10', '2026-03-11', ' 2026-03-10'],
        'a day-first spelling' => ['10/03/2026', '2026-03-11', '10/03/2026'],
        'plain words' => ['tomorrow', '2026-03-11', 'tomorrow'],
        'a malformed end' => ['2026-03-10', '2026-03-32', '2026-03-32'],
    ]);

    it('accepts the leap day of a leap year', function () {
        $interval = (new AllDayEventSpan('2028-02-29', '2028-03-01'))->intervalIn($this->madrid);

        expect($interval->startsAt->format(DATE_ATOM))->toBe('2028-02-28T23:00:00+00:00');
    });

    it('refuses an event whose end is its start', function () {
        expect(fn () => new AllDayEventSpan('2026-03-10', '2026-03-10'))
            ->toThrow(InvalidExternalCalendarEvent::class, 'An external calendar event must end after it starts.');
    });

    it('refuses an event that ends before it starts', function () {
        expect(fn () => new AllDayEventSpan('2026-03-11', '2026-03-10'))
            ->toThrow(InvalidExternalCalendarEvent::class, 'An external calendar event must end after it starts.');
    });

    it('refuses an event that ends in an earlier year', function () {
        expect(fn () => new AllDayEventSpan('2027-01-01', '2026-12-31'))
            ->toThrow(InvalidExternalCalendarEvent::class, 'An external calendar event must end after it starts.');
    });

    it('refuses with an invalid failure the transport can classify', function () {
        $failure = null;

        try {
            new AllDayEventSpan('2026-02-30', '2026-03-02');
        } catch (InvalidExternalCalendarEvent $refused) {
            $failure = $refused;
        }

        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure?->errorCode())->toBe('invalid_external_calendar_event')
            ->and($failure?->kind())->toBe(DomainFailureKind::Invalid);
    });
});
