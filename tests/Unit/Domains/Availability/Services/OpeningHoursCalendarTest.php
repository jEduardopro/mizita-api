<?php

declare(strict_types=1);

use App\Domains\Availability\Services\OpeningHoursCalendar;
use App\Domains\Availability\ValueObjects\OpenState;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use Tests\Support\Availability\ScheduleFixtures;

function madridZone(): DateTimeZone
{
    return new DateTimeZone('Europe/Madrid');
}

function madridStateAt(WeeklyIntervals $hours, string $instant): OpenState
{
    return (new OpeningHoursCalendar)->stateAt($hours, madridZone(), new DateTimeImmutable($instant));
}

function weekdayHours(): WeeklyIntervals
{
    return ScheduleFixtures::weeklyIntervals([
        1 => [['09:00', '14:00'], ['16:00', '20:00']],
        2 => [['09:00', '14:00'], ['16:00', '20:00']],
        3 => [['09:00', '14:00'], ['16:00', '20:00']],
        4 => [['09:00', '14:00'], ['16:00', '20:00']],
        5 => [['09:00', '14:00'], ['16:00', '20:00']],
    ]);
}

describe('the minute a business counts as open', function () {
    it('is open at the exact minute the doors open', function () {
        $state = madridStateAt(weekdayHours(), '2026-03-10T08:00:00+00:00');

        expect($state->isOpen())->toBeTrue()
            ->and($state->closesAt?->toString())->toBe('14:00')
            ->and($state->opensOn)->toBeNull()
            ->and($state->opensAt)->toBeNull();
    });

    it('is open one minute before the doors close', function () {
        $state = madridStateAt(weekdayHours(), '2026-03-10T12:59:00+00:00');

        expect($state->isOpen())->toBeTrue()
            ->and($state->closesAt?->toString())->toBe('14:00');
    });

    it('is closed at the exact minute the doors close', function () {
        $state = madridStateAt(weekdayHours(), '2026-03-10T13:00:00+00:00');

        expect($state->isOpen())->toBeFalse()
            ->and($state->closesAt)->toBeNull();
    });

    it('is closed in the gap between two intervals of the same day', function () {
        expect(madridStateAt(weekdayHours(), '2026-03-10T14:00:00+00:00')->isOpen())->toBeFalse();
    });

    it('is closed before the doors have opened at all', function () {
        expect(madridStateAt(weekdayHours(), '2026-03-10T07:00:00+00:00')->isOpen())->toBeFalse();
    });

    it('is closed on a weekday the business publishes no hours for', function () {
        expect(madridStateAt(weekdayHours(), '2026-03-14T11:00:00+00:00')->isOpen())->toBeFalse();
    });

    it('is open again after the gap, in the second interval of the day', function () {
        $state = madridStateAt(weekdayHours(), '2026-03-10T15:30:00+00:00');

        expect($state->isOpen())->toBeTrue()
            ->and($state->closesAt?->toString())->toBe('20:00');
    });
});

describe('when a closed business opens next', function () {
    it('opens later the same day when the gap is between two intervals', function () {
        $state = madridStateAt(weekdayHours(), '2026-03-10T14:00:00+00:00');

        expect($state->opensOn)->toBe(Weekday::Tuesday)
            ->and($state->opensAt?->toString())->toBe('16:00');
    });

    it('opens later the same day at the very minute the doors closed', function () {
        $state = madridStateAt(weekdayHours(), '2026-03-10T13:00:00+00:00');

        expect($state->opensOn)->toBe(Weekday::Tuesday)
            ->and($state->opensAt?->toString())->toBe('16:00');
    });

    it('opens the same morning when the business has not opened yet today', function () {
        $state = madridStateAt(weekdayHours(), '2026-03-10T07:00:00+00:00');

        expect($state->opensOn)->toBe(Weekday::Tuesday)
            ->and($state->opensAt?->toString())->toBe('09:00');
    });

    it('opens the next morning once the last interval of a weekday has ended', function () {
        $state = madridStateAt(weekdayHours(), '2026-03-10T20:00:00+00:00');

        expect($state->opensOn)->toBe(Weekday::Wednesday)
            ->and($state->opensAt?->toString())->toBe('09:00');
    });

    it('opens on Monday when the visitor reads the page on a Saturday', function () {
        $state = madridStateAt(weekdayHours(), '2026-03-14T11:00:00+00:00');

        expect($state->opensOn)->toBe(Weekday::Monday)
            ->and($state->opensAt?->toString())->toBe('09:00');
    });

    it('opens on Monday when the visitor reads the page on a Sunday', function () {
        expect(madridStateAt(weekdayHours(), '2026-03-15T11:00:00+00:00')->opensOn)->toBe(Weekday::Monday);
    });

    it('answers a different next opening for the weekend, the evening and the lunch gap', function () {
        $hours = weekdayHours();

        $fromSaturday = madridStateAt($hours, '2026-03-14T11:00:00+00:00');
        $fromEvening = madridStateAt($hours, '2026-03-10T20:00:00+00:00');
        $fromGap = madridStateAt($hours, '2026-03-10T14:00:00+00:00');

        expect([$fromSaturday->opensOn, $fromSaturday->opensAt?->toString()])->toBe([Weekday::Monday, '09:00'])
            ->and([$fromEvening->opensOn, $fromEvening->opensAt?->toString()])->toBe([Weekday::Wednesday, '09:00'])
            ->and([$fromGap->opensOn, $fromGap->opensAt?->toString()])->toBe([Weekday::Tuesday, '16:00']);
    });
});

describe('a business with no weekly hours at all', function () {
    it('is closed with no next opening to promise', function () {
        $state = madridStateAt(WeeklyIntervals::none(), '2026-03-10T08:00:00+00:00');

        expect($state->isOpen())->toBeFalse()
            ->and($state->closesAt)->toBeNull()
            ->and($state->opensOn)->toBeNull()
            ->and($state->opensAt)->toBeNull();
    });

    it('is closed on every weekday alike, so no day looks different', function () {
        $states = array_map(
            static fn (int $day): bool => madridStateAt(
                WeeklyIntervals::none(),
                '2026-03-'.str_pad((string) (8 + $day), 2, '0', STR_PAD_LEFT).'T12:00:00+00:00',
            )->isOpen(),
            range(0, 6),
        );

        expect($states)->toBe(array_fill(0, 7, false));
    });
});

describe('the two days a year the local clock jumps', function () {
    it('never opens during the local hour a spring-forward Sunday skips', function () {
        $hours = ScheduleFixtures::weeklyIntervals([7 => [['02:00', '03:00']]]);
        $midnightLocal = new DateTimeImmutable('2026-03-28T23:00:00+00:00');

        $states = array_map(
            static fn (int $minute): bool => (new OpeningHoursCalendar)->stateAt(
                $hours,
                madridZone(),
                $midnightLocal->add(new DateInterval('PT'.$minute.'M')),
            )->isOpen(),
            range(0, 1439),
        );

        expect(array_unique($states))->toBe([false]);
    });

    it('opens during that same local hour on an ordinary Sunday, so the rule is not vacuous', function () {
        $hours = ScheduleFixtures::weeklyIntervals([7 => [['02:00', '03:00']]]);

        expect(madridStateAt($hours, '2026-03-22T01:30:00+00:00')->isOpen())->toBeTrue();
    });

    it('reads the local hour through the summer offset once a spring-forward Sunday has turned', function () {
        $hours = ScheduleFixtures::weeklyIntervals([7 => [['09:00', '14:00']]]);
        $state = madridStateAt($hours, '2026-03-29T07:30:00+00:00');

        expect($state->isOpen())->toBeTrue()
            ->and($state->closesAt?->toString())->toBe('14:00');
    });

    it('reads the same instant of the previous Sunday through the winter offset, and finds it closed', function () {
        $hours = ScheduleFixtures::weeklyIntervals([7 => [['09:00', '14:00']]]);
        $state = madridStateAt($hours, '2026-03-22T07:30:00+00:00');

        expect($state->isOpen())->toBeFalse()
            ->and($state->opensOn)->toBe(Weekday::Sunday)
            ->and($state->opensAt?->toString())->toBe('09:00');
    });

    it('is open on both passes through the local hour a fall-back Sunday repeats', function () {
        $hours = ScheduleFixtures::weeklyIntervals([7 => [['02:00', '03:00']]]);

        $firstPass = madridStateAt($hours, '2026-10-25T00:30:00+00:00');
        $secondPass = madridStateAt($hours, '2026-10-25T01:30:00+00:00');

        expect($firstPass->isOpen())->toBeTrue()
            ->and($firstPass->closesAt?->toString())->toBe('03:00')
            ->and($secondPass->isOpen())->toBeTrue()
            ->and($secondPass->closesAt?->toString())->toBe('03:00');
    });

    it('is closed once the second pass through the repeated hour has ended', function () {
        $hours = ScheduleFixtures::weeklyIntervals([7 => [['02:00', '03:00']]]);

        expect(madridStateAt($hours, '2026-10-25T02:00:00+00:00')->isOpen())->toBeFalse();
    });

    it('opens a week later, at the same local time, once a fall-back Sunday is over', function () {
        $hours = ScheduleFixtures::weeklyIntervals([7 => [['02:00', '03:00']]]);
        $state = madridStateAt($hours, '2026-10-25T02:00:00+00:00');

        expect($state->opensOn)->toBe(Weekday::Sunday)
            ->and($state->opensAt?->toString())->toBe('02:00');
    });
});
