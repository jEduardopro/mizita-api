<?php

declare(strict_types=1);

use App\Domains\Availability\ValueObjects\ScheduleInterval;
use App\Domains\Availability\ValueObjects\TimeOfDay;
use App\Domains\Availability\ValueObjects\Weekday;

it('carries the day it recurs on and the two local times it spans', function () {
    $interval = new ScheduleInterval(
        weekday: Weekday::Wednesday,
        startsAt: TimeOfDay::fromString('09:00'),
        endsAt: TimeOfDay::fromString('14:00'),
    );

    expect($interval->weekday)->toBe(Weekday::Wednesday)
        ->and($interval->startsAt->toString())->toBe('09:00')
        ->and($interval->endsAt->toString())->toBe('14:00');
});

it('takes an interval as asked, leaving the rules to the entity that is built from it', function () {
    $inverted = new ScheduleInterval(
        weekday: Weekday::Monday,
        startsAt: TimeOfDay::fromString('18:00'),
        endsAt: TimeOfDay::fromString('09:00'),
    );

    expect($inverted->startsAt->isBefore($inverted->endsAt))->toBeFalse();
});
