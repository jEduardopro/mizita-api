<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\InvalidCalendarRange;
use App\Domains\Appointments\ValueObjects\CalendarRange;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

describe('a range between two instants', function () {
    it('holds both ends as it was handed them', function () {
        $range = CalendarRange::between(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-01-08T00:00:00Z'),
        );

        expect($range->from)->toEqual(new DateTimeImmutable('2026-01-01T00:00:00Z'))
            ->and($range->to)->toEqual(new DateTimeImmutable('2026-01-08T00:00:00Z'));
    });

    it('accepts a window as wide as a calendar view may ask for', function () {
        $range = CalendarRange::between(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-03-04T00:00:00Z'),
        );

        expect($range->to->getTimestamp() - $range->from->getTimestamp())
            ->toBe(CalendarRange::MAXIMUM_SPAN_DAYS * 86400);
    });

    it('refuses a window one second wider than the widest allowed', function () {
        expect(fn () => CalendarRange::between(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-03-04T00:00:01Z'),
        ))->toThrow(InvalidCalendarRange::class, 'may not span more than 62 days');
    });

    it('refuses a window one day wider than the widest allowed', function () {
        expect(fn () => CalendarRange::between(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-03-05T00:00:00Z'),
        ))->toThrow(InvalidCalendarRange::class, 'may not span more than 62 days');
    });

    it('refuses a range that ends before it starts', function () {
        expect(fn () => CalendarRange::between(
            new DateTimeImmutable('2026-01-08T00:00:00Z'),
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
        ))->toThrow(InvalidCalendarRange::class, 'has to end after it starts');
    });

    it('refuses a range that ends at the very instant it starts', function () {
        expect(fn () => CalendarRange::between(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
        ))->toThrow(InvalidCalendarRange::class, 'has to end after it starts');
    });

    it('accepts the narrowest window there is, a single second', function () {
        $range = CalendarRange::between(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-01-01T00:00:01Z'),
        );

        expect($range->to->getTimestamp() - $range->from->getTimestamp())->toBe(1);
    });
});

describe('reading a range off the wire', function () {
    it('states both ends in UTC whatever offsets they arrived with', function () {
        $range = CalendarRange::fromStrings('2026-01-01T00:00:00+02:00', '2026-01-08T00:00:00-05:00');

        expect($range->from->format('Y-m-d H:i:s'))->toBe('2025-12-31 22:00:00')
            ->and($range->from->getTimezone()->getName())->toBe('UTC')
            ->and($range->to->format('Y-m-d H:i:s'))->toBe('2026-01-08 05:00:00')
            ->and($range->to->getTimezone()->getName())->toBe('UTC');
    });

    it('ignores padding around either end', function () {
        $range = CalendarRange::fromStrings('  2026-01-01T00:00:00Z ', " 2026-01-08T00:00:00Z\n");

        expect($range->from->format('Y-m-d'))->toBe('2026-01-01')
            ->and($range->to->format('Y-m-d'))->toBe('2026-01-08');
    });

    it('refuses a start that is not a readable instant', function (string $from) {
        expect(fn () => CalendarRange::fromStrings($from, '2026-01-08T00:00:00Z'))
            ->toThrow(InvalidCalendarRange::class, 'is not a readable pair of instants');
    })->with([
        'empty' => '',
        'a date alone' => '2026-01-01',
        'a wall clock with no zone' => '2026-01-01T00:00:00',
        'a word' => 'today',
        'a space instead of the T' => '2026-01-01 00:00:00Z',
        'an offset with no colon' => '2026-01-01T00:00:00+0200',
        'a thirteenth month' => '2026-13-01T00:00:00Z',
    ]);

    it('refuses an end that is not a readable instant', function () {
        expect(fn () => CalendarRange::fromStrings('2026-01-01T00:00:00Z', 'next week'))
            ->toThrow(InvalidCalendarRange::class, 'is not a readable pair of instants');
    });

    it('still judges the width of a range whose ends it could read', function () {
        expect(fn () => CalendarRange::fromStrings('2026-01-08T00:00:00Z', '2026-01-01T00:00:00Z'))
            ->toThrow(InvalidCalendarRange::class, 'has to end after it starts');
    });

    it('refuses with a failure the responder can classify', function (callable $attempt) {
        $refusal = null;

        try {
            $attempt();
        } catch (InvalidCalendarRange $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_calendar_range')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    })->with([
        'unreadable ends' => [fn () => CalendarRange::fromStrings('yesterday', 'today')],
        'an inverted range' => [fn () => CalendarRange::between(
            new DateTimeImmutable('2026-01-08T00:00:00Z'),
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
        )],
        'a range wider than the cap' => [fn () => CalendarRange::between(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-06-01T00:00:00Z'),
        )],
    ]);
});

describe('a range spanning a daylight saving boundary', function () {
    it('keeps a month of Madrid local time that is an hour short of its calendar length', function () {
        $range = CalendarRange::fromStrings('2026-03-01T00:00:00+01:00', '2026-04-01T00:00:00+02:00');

        expect($range->to->getTimestamp() - $range->from->getTimestamp())->toBe(31 * 86400 - 3600);
    });

    it('accepts the widest window allowed when the clocks going forward make it an hour shorter', function () {
        $range = CalendarRange::fromStrings('2026-02-26T00:00:00+01:00', '2026-04-29T00:00:00+02:00');

        expect($range->to->getTimestamp() - $range->from->getTimestamp())
            ->toBe(CalendarRange::MAXIMUM_SPAN_DAYS * 86400 - 3600);
    });

    it('measures the cap in real time, so sixty-two local days ending past the clocks going back run over it', function () {
        expect(fn () => CalendarRange::fromStrings('2026-09-01T00:00:00+02:00', '2026-11-02T00:00:00+01:00'))
            ->toThrow(InvalidCalendarRange::class, 'may not span more than 62 days');
    });

    it('keeps the local days a calendar asked for when the repeated hour still fits under the cap', function () {
        $range = CalendarRange::fromStrings('2026-10-01T00:00:00+02:00', '2026-11-01T00:00:00+01:00');

        expect($range->from->format('Y-m-d H:i'))->toBe('2026-09-30 22:00')
            ->and($range->to->format('Y-m-d H:i'))->toBe('2026-10-31 23:00')
            ->and($range->to->getTimestamp() - $range->from->getTimestamp())->toBe(31 * 86400 + 3600);
    });
});
