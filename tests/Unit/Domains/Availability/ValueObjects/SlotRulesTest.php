<?php

declare(strict_types=1);

use App\Domains\Availability\ValueObjects\SlotRules;

function horizonFrom(SlotRules $rules, string $from = '2026-01-01T00:00:00+00:00'): DateTimeImmutable
{
    return (new DateTimeImmutable($from))->add($rules->bookingHorizon());
}

describe('how far ahead the booking horizon reaches', function () {
    it('reaches the hard cap when the business set no booking window', function () {
        expect(horizonFrom(new SlotRules(0, null, 15))->format(DATE_ATOM))
            ->toBe('2027-01-01T00:00:00+00:00');
    });

    it('reaches exactly the window the business asked for', function (int $minutes, string $expected) {
        expect(horizonFrom(new SlotRules(0, $minutes, 15))->format(DATE_ATOM))->toBe($expected);
    })->with([
        'two hours' => [120, '2026-01-01T02:00:00+00:00'],
        'ninety minutes' => [90, '2026-01-01T01:30:00+00:00'],
        'one day' => [1440, '2026-01-02T00:00:00+00:00'],
        'a day and a half' => [2160, '2026-01-02T12:00:00+00:00'],
        'thirty days' => [43200, '2026-01-31T00:00:00+00:00'],
    ]);

    it('never collapses a sub-day window to no reach at all', function (int $minutes) {
        $horizon = horizonFrom(new SlotRules(0, $minutes, 15));

        expect($horizon)->toBeGreaterThan(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    })->with([
        'one minute' => 1,
        'fifteen minutes' => 15,
        'an hour' => 60,
        'just under a day' => 1439,
    ]);

    it('reaches nowhere at all for a window of no minutes, because that is what it says', function () {
        expect(horizonFrom(new SlotRules(0, 0, 15))->format(DATE_ATOM))
            ->toBe('2026-01-01T00:00:00+00:00');
    });

    it('clamps a window wider than the hard cap back to the hard cap', function (int $days) {
        expect(horizonFrom(new SlotRules(0, $days * 24 * 60, 15))->format(DATE_ATOM))
            ->toBe('2027-01-01T00:00:00+00:00');
    })->with([
        'one day over' => SlotRules::HARD_CAP_DAYS + 1,
        'a month over' => SlotRules::HARD_CAP_DAYS + 30,
        'a decade' => 3650,
    ]);

    it('reaches the hard cap itself without clamping it away', function () {
        expect(horizonFrom(new SlotRules(0, SlotRules::HARD_CAP_DAYS * 24 * 60, 15))->format(DATE_ATOM))
            ->toBe('2027-01-01T00:00:00+00:00');
    });

    it('caps at a year, which is the number the product committed to', function () {
        expect(SlotRules::HARD_CAP_DAYS)->toBe(365);
    });
});

describe('the last start a visitor may still book', function () {
    it('reaches the hard cap of a year when the business set no booking window', function () {
        $lastStart = (new SlotRules(0, null, 15))->lastBookableStart(
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            new DateTimeZone('Europe/Madrid'),
        );

        expect($lastStart->format('Y-m-d'))->toBe('2027-01-01')
            ->and($lastStart->getTimezone()->getName())->toBe('Europe/Madrid');
    });

    it('reaches exactly the window the business asked for', function () {
        expect((new SlotRules(0, 43200, 15))->lastBookableStart(
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            new DateTimeZone('UTC'),
        )->format(DATE_ATOM))->toBe('2026-01-31T00:00:00+00:00');
    });

    it('clamps a window wider than a year back to the hard cap', function () {
        expect((new SlotRules(0, (SlotRules::HARD_CAP_DAYS + 30) * 24 * 60, 15))->lastBookableStart(
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            new DateTimeZone('UTC'),
        )->format(DATE_ATOM))->toBe('2027-01-01T00:00:00+00:00');
    });

    it('answers in the local time of the business, never in the zone the instant arrived in', function () {
        $lastStart = (new SlotRules(0, 1440, 15))->lastBookableStart(
            new DateTimeImmutable('2026-06-30T23:30:00+00:00'),
            new DateTimeZone('Europe/Madrid'),
        );

        expect($lastStart->format('Y-m-d H:i'))->toBe('2026-07-02 01:30');
    });

    it('keeps the local wall clock across a spring-forward day, because the day is a calendar day', function () {
        $lastStart = (new SlotRules(0, null, 15))->lastBookableStart(
            new DateTimeImmutable('2026-03-28T12:00:00+00:00'),
            new DateTimeZone('Europe/Madrid'),
        );

        expect($lastStart->format('Y-m-d H:i'))->toBe('2027-03-28 13:00');
    });
});

describe('the rules a business hands the calculator', function () {
    it('carries the three numbers the calculator asks it for', function () {
        $rules = new SlotRules(30, 43200, 15);

        expect($rules->leadTimeMinutes)->toBe(30)
            ->and($rules->bookingWindowMinutes)->toBe(43200)
            ->and($rules->slotGranularityMinutes)->toBe(15);
    });

    it('reads an unlimited booking window as null, never as zero', function () {
        expect((new SlotRules(0, null, 15))->bookingWindowMinutes)->toBeNull();
    });
});
