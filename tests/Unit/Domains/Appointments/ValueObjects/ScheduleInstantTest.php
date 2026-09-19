<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\InvalidAppointmentSchedule;
use App\Domains\Appointments\ValueObjects\ScheduleInstant;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

describe('reading an instant off the wire', function () {
    it('keeps the moment it was handed and states it in UTC', function () {
        $instant = ScheduleInstant::fromString('2026-01-01T12:00:00Z');

        expect($instant->value->format('Y-m-d H:i:s'))->toBe('2026-01-01 12:00:00')
            ->and($instant->value->getTimezone()->getName())->toBe('UTC');
    });

    it('moves an offset instant to the same moment written in UTC', function (string $raw, string $utc) {
        expect(ScheduleInstant::fromString($raw)->value->format('Y-m-d H:i:s'))->toBe($utc);
    })->with([
        'ahead of UTC' => ['2026-01-01T14:00:00+02:00', '2026-01-01 12:00:00'],
        'behind UTC' => ['2026-01-01T06:00:00-06:00', '2026-01-01 12:00:00'],
        'zero offset spelled out' => ['2026-01-01T12:00:00+00:00', '2026-01-01 12:00:00'],
        'across the date line into the previous day' => ['2026-01-01T09:00:00+13:00', '2025-12-31 20:00:00'],
    ]);

    it('keeps the sub-second precision the caller sent', function () {
        expect(ScheduleInstant::fromString('2026-01-01T12:00:00.123456Z')->value->format('u'))->toBe('123456');
    });

    it('ignores padding around the value', function () {
        expect(ScheduleInstant::fromString("  2026-01-01T12:00:00Z \n")->value->format('H:i'))->toBe('12:00');
    });

    it('reads no instant as none rather than as a refusal', function (?string $raw) {
        expect(ScheduleInstant::fromNullable($raw))->toBeNull();
    })->with(['null' => null, 'empty' => '', 'spaces' => '   ', 'tab' => "\t", 'newline' => "\n"]);

    it('still refuses a value that is present but unreadable when it may be absent', function () {
        expect(fn () => ScheduleInstant::fromNullable('tomorrow'))->toThrow(InvalidAppointmentSchedule::class);
    });
});

describe('refusing what is not an instant', function () {
    it('refuses anything that is not an offset-bearing ISO-8601 instant', function (string $raw) {
        expect(fn () => ScheduleInstant::fromString($raw))
            ->toThrow(InvalidAppointmentSchedule::class, 'is not a readable instant');
    })->with([
        'empty' => '',
        'a word' => 'tomorrow',
        'a date alone' => '2026-01-01',
        'a space instead of the T' => '2026-01-01 12:00:00Z',
        'no seconds' => '2026-01-01T12:00Z',
        'an offset with no colon' => '2026-01-01T12:00:00+0200',
        'an unpadded month' => '2026-1-01T12:00:00Z',
        'a unix timestamp' => '1767268800',
        'a lowercase zulu' => '2026-01-01T12:00:00z',
        'a thirteenth month' => '2026-13-01T12:00:00Z',
        'a twenty-fifth hour' => '2026-01-01T25:00:00Z',
        'an offset of sixty minutes' => '2026-01-01T12:00:00+05:60',
        'sql injection' => "2026-01-01T12:00:00Z'; drop table appointments",
    ]);

    it('refuses a wall clock with no zone, because a local time is not a moment', function (string $raw) {
        expect(fn () => ScheduleInstant::fromString($raw))->toThrow(InvalidAppointmentSchedule::class);
    })->with([
        'an ordinary afternoon' => '2026-01-01T12:00:00',
        'the hour that does not exist when the clocks go forward' => '2026-03-29T02:30:00',
        'the hour that happens twice when the clocks go back' => '2026-10-25T02:30:00',
    ]);

    it('refuses with a failure the responder can classify', function () {
        $refusal = null;

        try {
            ScheduleInstant::fromString('not-an-instant');
        } catch (InvalidAppointmentSchedule $caught) {
            $refusal = $caught;
        }

        expect($refusal)->toBeInstanceOf(DomainFailure::class)
            ->and($refusal?->errorCode())->toBe('invalid_appointment_schedule')
            ->and($refusal?->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('refuses a day the calendar does not have rather than moving the appointment to another month', function () {
        expect(fn () => ScheduleInstant::fromString('2026-02-30T10:00:00Z'))
            ->toThrow(InvalidAppointmentSchedule::class);
    });
});

describe('the day the clocks go forward in Europe/Madrid', function () {
    it('reads the last minute before the jump as the UTC moment it is', function () {
        expect(ScheduleInstant::fromString('2026-03-29T01:59:00+01:00')->value->format('Y-m-d H:i:s'))
            ->toBe('2026-03-29 00:59:00');
    });

    it('reads the first minute after the jump as the UTC moment it is', function () {
        expect(ScheduleInstant::fromString('2026-03-29T03:00:00+02:00')->value->format('Y-m-d H:i:s'))
            ->toBe('2026-03-29 01:00:00');
    });

    it('puts one minute between the two local times the jump made an hour apart', function () {
        $before = ScheduleInstant::fromString('2026-03-29T01:59:00+01:00')->value;
        $after = ScheduleInstant::fromString('2026-03-29T03:00:00+02:00')->value;

        expect($after->getTimestamp() - $before->getTimestamp())->toBe(60);
    });
});

describe('the day the clocks go back in Europe/Madrid', function () {
    it('tells the two local half past twos apart by the offset each carries', function () {
        expect(ScheduleInstant::fromString('2026-10-25T02:30:00+02:00')->value->format('Y-m-d H:i:s'))
            ->toBe('2026-10-25 00:30:00')
            ->and(ScheduleInstant::fromString('2026-10-25T02:30:00+01:00')->value->format('Y-m-d H:i:s'))
            ->toBe('2026-10-25 01:30:00');
    });

    it('puts a full hour between two appointments booked at the same local time', function () {
        $first = ScheduleInstant::fromString('2026-10-25T02:30:00+02:00')->value;
        $second = ScheduleInstant::fromString('2026-10-25T02:30:00+01:00')->value;

        expect($second->getTimestamp() - $first->getTimestamp())->toBe(3600);
    });

    it('spells both of them as half past two again when read back in Madrid', function (string $raw) {
        expect(ScheduleInstant::fromString($raw)->value->setTimezone(new DateTimeZone('Europe/Madrid'))->format('H:i'))
            ->toBe('02:30');
    })->with([
        'before the clocks go back' => '2026-10-25T02:30:00+02:00',
        'after the clocks go back' => '2026-10-25T02:30:00+01:00',
    ]);
});

describe('America/Mexico_City, which stopped moving its clocks', function () {
    it('reads the same offset in June as in December', function (string $raw, string $utc) {
        expect(ScheduleInstant::fromString($raw)->value->format('Y-m-d H:i:s'))->toBe($utc);
    })->with([
        'midsummer' => ['2026-06-15T09:00:00-06:00', '2026-06-15 15:00:00'],
        'midwinter' => ['2026-12-15T09:00:00-06:00', '2026-12-15 15:00:00'],
    ]);

    it('reads a summer morning back as the same local hour a customer was told', function () {
        expect(
            ScheduleInstant::fromString('2026-06-15T09:00:00-06:00')
                ->value
                ->setTimezone(new DateTimeZone('America/Mexico_City'))
                ->format('H:i')
        )->toBe('09:00');
    });
});
