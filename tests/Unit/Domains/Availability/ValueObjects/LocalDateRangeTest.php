<?php

declare(strict_types=1);

use App\Domains\Availability\ValueObjects\LocalDateRange;

beforeEach(function () {
    $this->madrid = new DateTimeZone('Europe/Madrid');

    $this->clamp = fn (
        string $from,
        string $to,
        string $earliest,
        string $latest,
        ?DateTimeZone $zone = null,
    ): ?LocalDateRange => LocalDateRange::between($from, $to)->clampedTo(
        new DateTimeImmutable($earliest),
        new DateTimeImmutable($latest),
        $zone ?? $this->madrid,
    );

    $this->bounds = static fn (?LocalDateRange $range): ?array => $range === null ? null : [$range->from, $range->to];
});

describe('clamping a requested range to the bookable dates', function () {
    it('keeps a range that lies wholly inside the bookable dates', function () {
        expect(($this->bounds)(($this->clamp)(
            '2026-03-10',
            '2026-03-12',
            '2026-03-01T12:00:00+00:00',
            '2026-04-01T12:00:00+00:00',
        )))->toBe(['2026-03-10', '2026-03-12']);
    });

    it('moves the start up to today when the range begins in the past', function () {
        expect(($this->bounds)(($this->clamp)(
            '2026-03-01',
            '2026-03-12',
            '2026-03-10T12:00:00+00:00',
            '2026-04-01T12:00:00+00:00',
        )))->toBe(['2026-03-10', '2026-03-12']);
    });

    it('moves the end back to the last bookable date when the range runs past the horizon', function () {
        expect(($this->bounds)(($this->clamp)(
            '2026-03-10',
            '2026-03-31',
            '2026-03-01T12:00:00+00:00',
            '2026-03-15T12:00:00+00:00',
        )))->toBe(['2026-03-10', '2026-03-15']);
    });

    it('narrows both ends at once', function () {
        expect(($this->bounds)(($this->clamp)(
            '2026-03-01',
            '2026-03-31',
            '2026-03-10T12:00:00+00:00',
            '2026-03-15T12:00:00+00:00',
        )))->toBe(['2026-03-10', '2026-03-15']);
    });

    it('keeps a single day that is both today and the last bookable date', function () {
        expect(($this->bounds)(($this->clamp)(
            '2026-03-01',
            '2026-03-31',
            '2026-03-10T08:00:00+00:00',
            '2026-03-10T20:00:00+00:00',
        )))->toBe(['2026-03-10', '2026-03-10']);
    });

    it('answers with nothing when the range lies wholly before today', function () {
        expect(($this->clamp)(
            '2026-03-01',
            '2026-03-09',
            '2026-03-10T12:00:00+00:00',
            '2026-04-01T12:00:00+00:00',
        ))->toBeNull();
    });

    it('answers with nothing when the range lies wholly past the horizon', function () {
        expect(($this->clamp)(
            '2026-03-20',
            '2026-03-25',
            '2026-03-10T12:00:00+00:00',
            '2026-03-19T12:00:00+00:00',
        ))->toBeNull();
    });

    it('leaves the original range untouched', function () {
        $requested = LocalDateRange::between('2026-03-01', '2026-03-31');

        $requested->clampedTo(
            new DateTimeImmutable('2026-03-10T12:00:00+00:00'),
            new DateTimeImmutable('2026-03-15T12:00:00+00:00'),
            $this->madrid,
        );

        expect([$requested->from, $requested->to])->toBe(['2026-03-01', '2026-03-31']);
    });
});

describe('the zone the bookable dates are read in', function () {
    it('reads today as the local date, which may already be tomorrow in UTC terms', function () {
        expect(($this->bounds)(($this->clamp)(
            '2026-03-10',
            '2026-03-12',
            '2026-03-10T23:30:00+00:00',
            '2026-04-01T12:00:00+00:00',
        )))->toBe(['2026-03-11', '2026-03-12']);
    });

    it('reads the same instant as today in a zone still on the previous date', function () {
        expect(($this->bounds)(($this->clamp)(
            '2026-03-10',
            '2026-03-12',
            '2026-03-10T23:30:00+00:00',
            '2026-04-01T12:00:00+00:00',
            new DateTimeZone('America/Mexico_City'),
        )))->toBe(['2026-03-10', '2026-03-12']);
    });

    it('drops a day that has already ended locally even though it has not in UTC', function () {
        expect(($this->clamp)(
            '2026-03-10',
            '2026-03-10',
            '2026-03-10T23:30:00+00:00',
            '2026-04-01T12:00:00+00:00',
        ))->toBeNull();
    });

    it('reads the dates right on a daylight saving day', function (string $from, string $to, string $earliest, string $latest, array $expected) {
        expect(($this->bounds)(($this->clamp)($from, $to, $earliest, $latest)))->toBe($expected);
    })->with([
        'spring forward, just after the missing hour' => [
            '2026-03-20', '2026-04-10',
            '2026-03-29T01:30:00+00:00',
            '2026-03-29T21:59:00+00:00',
            ['2026-03-29', '2026-03-29'],
        ],
        'spring forward, the shortened day from midnight to midnight' => [
            '2026-03-20', '2026-04-10',
            '2026-03-28T23:00:00+00:00',
            '2026-03-29T22:00:00+00:00',
            ['2026-03-29', '2026-03-30'],
        ],
        'fall back, the second 02:30 of the day' => [
            '2026-10-15', '2026-11-05',
            '2026-10-25T01:30:00+00:00',
            '2026-10-25T22:59:00+00:00',
            ['2026-10-25', '2026-10-25'],
        ],
        'fall back, the lengthened day from midnight to midnight' => [
            '2026-10-15', '2026-11-05',
            '2026-10-24T22:00:00+00:00',
            '2026-10-25T23:00:00+00:00',
            ['2026-10-25', '2026-10-26'],
        ],
    ]);
});
