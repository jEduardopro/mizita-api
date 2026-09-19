<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\InvalidAppointmentSchedule;
use App\Domains\Appointments\ValueObjects\AppointmentSlot;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

describe('a slot between two instants', function () {
    it('holds both ends as it was handed them', function () {
        $slot = AppointmentSlot::between(
            new DateTimeImmutable('2026-01-01T09:00:00Z'),
            new DateTimeImmutable('2026-01-01T10:00:00Z'),
        );

        expect($slot->startsAt)->toEqual(new DateTimeImmutable('2026-01-01T09:00:00Z'))
            ->and($slot->endsAt)->toEqual(new DateTimeImmutable('2026-01-01T10:00:00Z'));
    });

    it('reports how many minutes it runs for', function () {
        $slot = AppointmentSlot::between(
            new DateTimeImmutable('2026-01-01T09:00:00Z'),
            new DateTimeImmutable('2026-01-01T09:45:00Z'),
        );

        expect($slot->durationMinutes())->toBe(45);
    });

    it('measures the gap in absolute time even when the two ends are written in different zones', function () {
        $slot = AppointmentSlot::between(
            new DateTimeImmutable('2026-01-01T10:00:00+01:00'),
            new DateTimeImmutable('2026-01-01T10:00:00Z'),
        );

        expect($slot->durationMinutes())->toBe(60);
    });

    it('rounds a duration that is not whole minutes down to the minute', function () {
        $slot = AppointmentSlot::between(
            new DateTimeImmutable('2026-01-01T09:00:00Z'),
            new DateTimeImmutable('2026-01-01T09:01:30Z'),
        );

        expect($slot->durationMinutes())->toBe(1);
    });

    it('accepts a slot running exactly as long as a slot may run', function () {
        $slot = AppointmentSlot::between(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-01-02T00:00:00Z'),
        );

        expect($slot->durationMinutes())->toBe(AppointmentSlot::MAXIMUM_DURATION_MINUTES);
    });

    it('refuses a slot a single minute past the longest one allowed', function () {
        expect(fn () => AppointmentSlot::between(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-01-02T00:01:00Z'),
        ))->toThrow(InvalidAppointmentSchedule::class, 'may not run past 1440 minutes');
    });

    it('refuses a slot one second past the longest one allowed', function () {
        expect(fn () => AppointmentSlot::between(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-01-02T00:00:01Z'),
        ))->toThrow(InvalidAppointmentSchedule::class, 'may not run past 1440 minutes');
    });

    it('refuses a slot that ends before it starts', function () {
        expect(fn () => AppointmentSlot::between(
            new DateTimeImmutable('2026-01-01T10:00:00Z'),
            new DateTimeImmutable('2026-01-01T09:00:00Z'),
        ))->toThrow(InvalidAppointmentSchedule::class, 'has to end after it starts');
    });

    it('refuses a slot that ends at the very instant it starts', function () {
        expect(fn () => AppointmentSlot::between(
            new DateTimeImmutable('2026-01-01T10:00:00Z'),
            new DateTimeImmutable('2026-01-01T10:00:00Z'),
        ))->toThrow(InvalidAppointmentSchedule::class);
    });

    it('refuses a slot shorter than the minute it must last', function () {
        expect(fn () => AppointmentSlot::between(
            new DateTimeImmutable('2026-01-01T10:00:00Z'),
            new DateTimeImmutable('2026-01-01T10:00:59Z'),
        ))->toThrow(InvalidAppointmentSchedule::class);
    });

    it('accepts a slot of exactly the shortest minute allowed', function () {
        $slot = AppointmentSlot::between(
            new DateTimeImmutable('2026-01-01T10:00:00Z'),
            new DateTimeImmutable('2026-01-01T10:01:00Z'),
        );

        expect($slot->durationMinutes())->toBe(AppointmentSlot::MINIMUM_DURATION_MINUTES);
    });
});

describe('a slot lasting a set number of minutes', function () {
    it('ends the given number of minutes after it starts', function () {
        $slot = AppointmentSlot::lasting(new DateTimeImmutable('2026-01-01T09:00:00Z'), 30);

        expect($slot->endsAt)->toEqual(new DateTimeImmutable('2026-01-01T09:30:00Z'))
            ->and($slot->durationMinutes())->toBe(30);
    });

    it('leaves the start exactly where it was told to start', function () {
        $slot = AppointmentSlot::lasting(new DateTimeImmutable('2026-01-01T09:00:00Z'), 30);

        expect($slot->startsAt)->toEqual(new DateTimeImmutable('2026-01-01T09:00:00Z'));
    });

    it('accepts a one minute appointment, the shortest there is', function () {
        expect(AppointmentSlot::lasting(new DateTimeImmutable('2026-01-01T09:00:00Z'), 1)->durationMinutes())
            ->toBe(1);
    });

    it('accepts an appointment running exactly as long as one may run', function () {
        expect(AppointmentSlot::lasting(
            new DateTimeImmutable('2026-01-01T09:00:00Z'),
            AppointmentSlot::MAXIMUM_DURATION_MINUTES,
        )->durationMinutes())->toBe(AppointmentSlot::MAXIMUM_DURATION_MINUTES);
    });

    it('refuses an appointment a minute past the longest one allowed', function () {
        expect(fn () => AppointmentSlot::lasting(
            new DateTimeImmutable('2026-01-01T09:00:00Z'),
            AppointmentSlot::MAXIMUM_DURATION_MINUTES + 1,
        ))->toThrow(InvalidAppointmentSchedule::class, 'may not run past 1440 minutes');
    });

    it('refuses an appointment that lasts no time at all', function (int $minutes) {
        expect(fn () => AppointmentSlot::lasting(new DateTimeImmutable('2026-01-01T09:00:00Z'), $minutes))
            ->toThrow(InvalidAppointmentSchedule::class, 'has to last at least one minute');
    })->with(['nothing' => 0, 'a minute backwards' => -1, 'an hour backwards' => -60]);

    it('crosses midnight without losing an appointment', function () {
        $slot = AppointmentSlot::lasting(new DateTimeImmutable('2026-01-01T23:30:00Z'), 60);

        expect($slot->endsAt->format('Y-m-d H:i'))->toBe('2026-01-02 00:30')
            ->and($slot->durationMinutes())->toBe(60);
    });
});

describe('a slot booked across a daylight saving boundary', function () {
    it('lasts a real hour when the clocks jump forward in the middle of it', function () {
        $slot = AppointmentSlot::lasting(
            new DateTimeImmutable('2026-03-29 01:30:00', new DateTimeZone('Europe/Madrid')),
            60,
        );

        expect($slot->durationMinutes())->toBe(60)
            ->and($slot->endsAt->format('H:i P'))->toBe('03:30 +02:00');
    });

    it('lands the customer at the local time the clock on the wall will show after the jump', function () {
        $slot = AppointmentSlot::lasting(
            new DateTimeImmutable('2026-03-29T01:30:00+01:00'),
            60,
        );

        expect($slot->endsAt->setTimezone(new DateTimeZone('Europe/Madrid'))->format('H:i'))->toBe('03:30');
    });

    it('lasts ninety real minutes when the clocks go back in the middle of it', function () {
        $slot = AppointmentSlot::lasting(new DateTimeImmutable('2026-10-25T02:30:00+02:00'), 90);

        expect($slot->durationMinutes())->toBe(90)
            ->and($slot->endsAt->setTimezone(new DateTimeZone('Europe/Madrid'))->format('H:i'))->toBe('03:00');
    });

    it('counts the repeated hour when it sits between two instants written with their own offsets', function () {
        $slot = AppointmentSlot::between(
            new DateTimeImmutable('2026-10-25T02:30:00+02:00'),
            new DateTimeImmutable('2026-10-25T02:30:00+01:00'),
        );

        expect($slot->durationMinutes())->toBe(60);
    });

    it('never quietly turns an hour booked over the jump into no time at all', function () {
        $slot = AppointmentSlot::between(
            new DateTimeImmutable('2026-03-29T01:30:00+01:00'),
            new DateTimeImmutable('2026-03-29T03:30:00+02:00'),
        );

        expect($slot->durationMinutes())->toBe(60);
    });
});

describe('restoring a slot from persistence', function () {
    it('takes the row as it stands rather than judging it again', function () {
        $slot = AppointmentSlot::restore(
            new DateTimeImmutable('2026-01-01T10:00:00Z'),
            new DateTimeImmutable('2026-01-01T09:00:00Z'),
        );

        expect($slot->durationMinutes())->toBe(-60);
    });

    it('restores a booking longer than any that could be made today', function () {
        $slot = AppointmentSlot::restore(
            new DateTimeImmutable('2026-01-01T00:00:00Z'),
            new DateTimeImmutable('2026-01-05T00:00:00Z'),
        );

        expect($slot->durationMinutes())->toBe(5760);
    });
});

it('refuses with a failure the responder can classify', function (callable $attempt) {
    $refusal = null;

    try {
        $attempt();
    } catch (InvalidAppointmentSchedule $caught) {
        $refusal = $caught;
    }

    expect($refusal)->toBeInstanceOf(DomainFailure::class)
        ->and($refusal?->errorCode())->toBe('invalid_appointment_schedule')
        ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'an inverted slot' => [fn () => AppointmentSlot::between(
        new DateTimeImmutable('2026-01-01T10:00:00Z'),
        new DateTimeImmutable('2026-01-01T09:00:00Z'),
    )],
    'a slot longer than a day' => [fn () => AppointmentSlot::lasting(
        new DateTimeImmutable('2026-01-01T09:00:00Z'),
        1441,
    )],
    'an appointment lasting no time' => [fn () => AppointmentSlot::lasting(
        new DateTimeImmutable('2026-01-01T09:00:00Z'),
        0,
    )],
]);
