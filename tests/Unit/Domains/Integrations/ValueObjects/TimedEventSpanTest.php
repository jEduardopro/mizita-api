<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\InvalidExternalCalendarEvent;
use App\Domains\Integrations\ValueObjects\EventSpan;
use App\Domains\Integrations\ValueObjects\TimedEventSpan;

it('is a span an external event can carry', function () {
    $span = new TimedEventSpan(
        new DateTimeImmutable('2026-03-10T09:00:00+00:00'),
        new DateTimeImmutable('2026-03-10T10:00:00+00:00'),
    );

    expect($span)->toBeInstanceOf(EventSpan::class);
});

it('blocks exactly the instants the event runs between', function () {
    $interval = (new TimedEventSpan(
        new DateTimeImmutable('2026-03-10T10:00:00+01:00'),
        new DateTimeImmutable('2026-03-10T11:30:00+01:00'),
    ))->intervalIn(new DateTimeZone('Europe/Madrid'));

    expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-10T09:00:00+00:00')
        ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-10T10:30:00+00:00');
});

it('blocks the same instants whatever zone the business is in', function (string $zone) {
    $interval = (new TimedEventSpan(
        new DateTimeImmutable('2026-03-10T09:00:00+00:00'),
        new DateTimeImmutable('2026-03-10T10:00:00+00:00'),
    ))->intervalIn(new DateTimeZone($zone));

    expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-10T09:00:00+00:00')
        ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-10T10:00:00+00:00');
})->with(['Europe/Madrid', 'America/Mexico_City', 'Asia/Tokyo', 'UTC']);

it('measures an event across the spring-forward night by the clock, not by the wall', function () {
    $interval = (new TimedEventSpan(
        new DateTimeImmutable('2026-03-29T01:30:00', new DateTimeZone('Europe/Madrid')),
        new DateTimeImmutable('2026-03-29T03:30:00', new DateTimeZone('Europe/Madrid')),
    ))->intervalIn(new DateTimeZone('Europe/Madrid'));

    expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-29T00:30:00+00:00')
        ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-29T01:30:00+00:00');
});

it('keeps both passes of the repeated hour on the fall-back night apart', function () {
    $interval = (new TimedEventSpan(
        new DateTimeImmutable('2026-10-25T02:30:00+02:00'),
        new DateTimeImmutable('2026-10-25T02:30:00+01:00'),
    ))->intervalIn(new DateTimeZone('Europe/Madrid'));

    expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-10-25T00:30:00+00:00')
        ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-10-25T01:30:00+00:00');
});

it('accepts an end that reads earlier on the wall but falls later in time', function () {
    $span = new TimedEventSpan(
        new DateTimeImmutable('2026-03-10T10:00:00+02:00'),
        new DateTimeImmutable('2026-03-10T08:30:00+00:00'),
    );

    expect($span->intervalIn(new DateTimeZone('UTC'))->endsAt->format(DATE_ATOM))->toBe('2026-03-10T08:30:00+00:00');
});

it('refuses an event that does not end after it starts', function (string $startsAt, string $endsAt) {
    expect(fn () => new TimedEventSpan(new DateTimeImmutable($startsAt), new DateTimeImmutable($endsAt)))
        ->toThrow(InvalidExternalCalendarEvent::class, 'An external calendar event must end after it starts.');
})->with([
    'no length at all' => ['2026-03-10T09:00:00+00:00', '2026-03-10T09:00:00+00:00'],
    'the same instant under another offset' => ['2026-03-10T10:00:00+01:00', '2026-03-10T09:00:00+00:00'],
    'an end before the start' => ['2026-03-10T10:00:00+00:00', '2026-03-10T09:00:00+00:00'],
    'an end one second before the start' => ['2026-03-10T09:00:00+00:00', '2026-03-10T08:59:59+00:00'],
]);
