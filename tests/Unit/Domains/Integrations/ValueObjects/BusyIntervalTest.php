<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\InvalidExternalCalendarEvent;
use App\Domains\Integrations\ValueObjects\BusyInterval;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('keeps the instants it was given', function () {
    $interval = new BusyInterval(
        new DateTimeImmutable('2026-03-10T09:00:00+00:00'),
        new DateTimeImmutable('2026-03-10T10:00:00+00:00'),
    );

    expect($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-10T09:00:00+00:00')
        ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-10T10:00:00+00:00');
});

it('stores the instants in UTC whatever offset they arrived with', function () {
    $interval = new BusyInterval(
        new DateTimeImmutable('2026-03-10T10:00:00', new DateTimeZone('Europe/Madrid')),
        new DateTimeImmutable('2026-03-10T04:00:00-06:00'),
    );

    expect($interval->startsAt->getTimezone()->getName())->toBe('UTC')
        ->and($interval->endsAt->getTimezone()->getName())->toBe('UTC')
        ->and($interval->startsAt->format(DATE_ATOM))->toBe('2026-03-10T09:00:00+00:00')
        ->and($interval->endsAt->format(DATE_ATOM))->toBe('2026-03-10T10:00:00+00:00');
});

it('does not move the instant when it changes the zone', function () {
    $startsAt = new DateTimeImmutable('2026-10-25T02:30:00+01:00');

    $interval = new BusyInterval($startsAt, new DateTimeImmutable('2026-10-25T03:30:00+01:00'));

    expect($interval->startsAt->getTimestamp())->toBe($startsAt->getTimestamp())
        ->and($startsAt->format(DATE_ATOM))->toBe('2026-10-25T02:30:00+01:00');
});

it('accepts the shortest interval there is', function () {
    $interval = new BusyInterval(
        new DateTimeImmutable('2026-03-10T09:00:00+00:00'),
        new DateTimeImmutable('2026-03-10T09:00:01+00:00'),
    );

    expect($interval->endsAt->getTimestamp() - $interval->startsAt->getTimestamp())->toBe(1);
});

it('refuses an interval that does not end after it starts', function (string $startsAt, string $endsAt) {
    expect(fn () => new BusyInterval(new DateTimeImmutable($startsAt), new DateTimeImmutable($endsAt)))
        ->toThrow(InvalidExternalCalendarEvent::class, 'An external calendar event must end after it starts.');
})->with([
    'no length at all' => ['2026-03-10T09:00:00+00:00', '2026-03-10T09:00:00+00:00'],
    'the same instant under another offset' => ['2026-03-10T10:00:00+01:00', '2026-03-10T09:00:00+00:00'],
    'an end before the start' => ['2026-03-10T10:00:00+00:00', '2026-03-10T09:00:00+00:00'],
]);

it('refuses with an invalid failure the transport can classify', function () {
    $failure = null;

    try {
        new BusyInterval(
            new DateTimeImmutable('2026-03-10T10:00:00+00:00'),
            new DateTimeImmutable('2026-03-10T09:00:00+00:00'),
        );
    } catch (InvalidExternalCalendarEvent $refused) {
        $failure = $refused;
    }

    expect($failure)->toBeInstanceOf(DomainFailure::class)
        ->and($failure?->errorCode())->toBe('invalid_external_calendar_event')
        ->and($failure?->kind())->toBe(DomainFailureKind::Invalid);
});
