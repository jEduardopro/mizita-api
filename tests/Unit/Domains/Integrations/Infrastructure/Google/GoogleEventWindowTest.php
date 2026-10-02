<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Google\GoogleEventWindow;

beforeEach(function () {
    $this->window = new GoogleEventWindow(
        new DateTimeImmutable('2026-03-10T00:00:00+01:00'),
        new DateTimeImmutable('2026-03-11T00:00:00+01:00'),
    );

    $this->timed = static fn (string $startsAt, string $endsAt): array => [
        'start' => ['dateTime' => $startsAt],
        'end' => ['dateTime' => $endsAt],
    ];

    $this->allDay = static fn (string $startDate, string $endDate): array => [
        'start' => ['date' => $startDate],
        'end' => ['date' => $endDate],
    ];
});

describe('a timed event', function () {
    it('may overlap when any part of it falls inside the window', function (string $startsAt, string $endsAt) {
        expect($this->window->mayOverlap(($this->timed)($startsAt, $endsAt)))->toBeTrue();
    })->with([
        'wholly inside' => ['2026-03-10T10:00:00+01:00', '2026-03-10T11:00:00+01:00'],
        'across the start' => ['2026-03-09T23:00:00+01:00', '2026-03-10T00:30:00+01:00'],
        'across the end' => ['2026-03-10T23:30:00+01:00', '2026-03-11T01:00:00+01:00'],
        'covering the whole window' => ['2026-03-09T00:00:00+01:00', '2026-03-12T00:00:00+01:00'],
        'written in another offset' => ['2026-03-10T09:00:00+00:00', '2026-03-10T10:00:00+00:00'],
    ]);

    it('cannot overlap when it lies wholly outside or only touches an edge', function (string $startsAt, string $endsAt) {
        expect($this->window->mayOverlap(($this->timed)($startsAt, $endsAt)))->toBeFalse();
    })->with([
        'the day before' => ['2026-03-09T10:00:00+01:00', '2026-03-09T11:00:00+01:00'],
        'the day after' => ['2026-03-11T10:00:00+01:00', '2026-03-11T11:00:00+01:00'],
        'ending exactly at the start' => ['2026-03-09T23:00:00+01:00', '2026-03-10T00:00:00+01:00'],
        'starting exactly at the end' => ['2026-03-11T00:00:00+01:00', '2026-03-11T01:00:00+01:00'],
        'ending at the start, written in UTC' => ['2026-03-09T22:00:00+00:00', '2026-03-09T23:00:00+00:00'],
    ]);
});

describe('an all-day event', function () {
    it('may overlap when the date could reach the window in some timezone', function (string $startDate, string $endDate) {
        expect($this->window->mayOverlap(($this->allDay)($startDate, $endDate)))->toBeTrue();
    })->with([
        'the same day' => ['2026-03-10', '2026-03-11'],
        'the day before, which ends after the window opens west of UTC' => ['2026-03-09', '2026-03-10'],
        'the day after, which starts before the window closes east of UTC' => ['2026-03-11', '2026-03-12'],
        'a week spanning the window' => ['2026-03-07', '2026-03-14'],
    ]);

    it('cannot overlap when no timezone brings the date into the window', function (string $startDate, string $endDate) {
        expect($this->window->mayOverlap(($this->allDay)($startDate, $endDate)))->toBeFalse();
    })->with([
        'two days before' => ['2026-03-08', '2026-03-09'],
        'two days after' => ['2026-03-12', '2026-03-13'],
    ]);
});

describe('an event it cannot read', function () {
    it('keeps the event, because hiding a busy period is worse than offering one slot fewer', function (array $item) {
        expect($this->window->mayOverlap($item))->toBeTrue();
    })->with([
        'no boundaries at all' => [['id' => 'evt-1']],
        'no end' => [['start' => ['dateTime' => '2026-03-20T10:00:00+01:00']]],
        'no start' => [['end' => ['dateTime' => '2026-03-20T10:00:00+01:00']]],
        'a boundary that is not an object' => [['start' => '2026-03-20', 'end' => '2026-03-21']],
        'a malformed instant' => [['start' => ['dateTime' => 'garbage-instant'], 'end' => ['dateTime' => 'garbage-end']]],
        'a malformed date' => [['start' => ['date' => 'not-a-date'], 'end' => ['date' => '2026-03-21']]],
        'an empty boundary' => [['start' => [], 'end' => []]],
    ]);
});
