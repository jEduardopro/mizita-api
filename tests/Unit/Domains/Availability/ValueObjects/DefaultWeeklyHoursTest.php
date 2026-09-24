<?php

declare(strict_types=1);

use App\Domains\Availability\ValueObjects\DefaultWeeklyHours;
use App\Domains\Availability\ValueObjects\ScheduleInterval;
use App\Domains\Availability\ValueObjects\Weekday;

it('opens Monday to Friday, in the order of the week', function () {
    $intervals = DefaultWeeklyHours::intervals();

    expect(array_is_list($intervals))->toBeTrue()
        ->and($intervals)->each->toBeInstanceOf(ScheduleInterval::class)
        ->and(array_map(static fn (ScheduleInterval $interval): Weekday => $interval->weekday, $intervals))
        ->toBe([
            Weekday::Monday,
            Weekday::Tuesday,
            Weekday::Wednesday,
            Weekday::Thursday,
            Weekday::Friday,
        ]);
});

it('opens every working day from 09:00 to 18:00', function () {
    $spans = array_map(
        static fn (ScheduleInterval $interval): array => [$interval->startsAt->toString(), $interval->endsAt->toString()],
        DefaultWeeklyHours::intervals(),
    );

    expect($spans)->toBe(array_fill(0, 5, ['09:00', '18:00']));
});

it('leaves the weekend closed', function (Weekday $weekend) {
    $weekdays = array_map(
        static fn (ScheduleInterval $interval): Weekday => $interval->weekday,
        DefaultWeeklyHours::intervals(),
    );

    expect($weekdays)->not->toContain($weekend);
})->with([
    'saturday' => Weekday::Saturday,
    'sunday' => Weekday::Sunday,
]);

it('offers a nine hour day that opens before it closes, so every default can become a rule', function () {
    foreach (DefaultWeeklyHours::intervals() as $interval) {
        expect($interval->startsAt->isBefore($interval->endsAt))->toBeTrue()
            ->and($interval->endsAt->minutesFromMidnight() - $interval->startsAt->minutesFromMidnight())->toBe(540);
    }
});
