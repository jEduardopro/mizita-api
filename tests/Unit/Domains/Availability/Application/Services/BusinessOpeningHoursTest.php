<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Services\BusinessOpeningHours;
use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\Services\OpeningHoursCalendar;
use App\Domains\Availability\ValueObjects\OpenState;
use App\Domains\Availability\ValueObjects\Weekday;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;

beforeEach(function () {
    $this->schedules = Mockery::mock(StaffSchedules::class);
    $this->businessClock = Mockery::mock(BusinessClock::class);

    $this->stateAt = function (string $now): OpenState {
        return (new BusinessOpeningHours(
            $this->schedules,
            $this->businessClock,
            new OpeningHoursCalendar,
            new FakeClock(new DateTimeImmutable($now)),
        ))->stateOf(FakeBusinessContext::BUSINESS_ID);
    };

    $this->publishHours = function (WeeklyIntervals $hours, string $timezone = 'Europe/Madrid'): void {
        $this->schedules->shouldReceive('forBusiness')->andReturn($hours);
        $this->businessClock->shouldReceive('timezoneOf')->andReturn($timezone);
    };
});

describe('the hours the business itself keeps', function () {
    it('answers open while the injected clock sits inside them', function () {
        ($this->publishHours)(ScheduleFixtures::weeklyIntervals([2 => [['09:00', '14:00']]]));

        $state = ($this->stateAt)('2026-03-10T08:00:00+00:00');

        expect($state->isOpen())->toBeTrue()
            ->and($state->closesAt?->toString())->toBe('14:00');
    });

    it('answers closed while the injected clock sits outside them', function () {
        ($this->publishHours)(ScheduleFixtures::weeklyIntervals([2 => [['09:00', '14:00']]]));

        expect(($this->stateAt)('2026-03-10T14:00:00+00:00')->isOpen())->toBeFalse();
    });

    it('reads the same instant in the timezone of the business, never in the timezone of the server', function () {
        $hours = ScheduleFixtures::weeklyIntervals([2 => [['09:00', '14:00']]]);

        $this->schedules->shouldReceive('forBusiness')->andReturn($hours);
        $this->businessClock->shouldReceive('timezoneOf')->andReturn('Europe/Madrid', 'America/Monterrey');

        expect(($this->stateAt)('2026-03-10T08:30:00+00:00')->isOpen())->toBeTrue()
            ->and(($this->stateAt)('2026-03-10T08:30:00+00:00')->isOpen())->toBeFalse();
    });

    it('asks both ports about the business it was given', function () {
        $askedForSchedule = null;
        $askedForZone = null;

        $this->schedules->shouldReceive('forBusiness')->once()
            ->with(Mockery::capture($askedForSchedule))
            ->andReturn(WeeklyIntervals::none());
        $this->businessClock->shouldReceive('timezoneOf')->once()
            ->with(Mockery::capture($askedForZone))
            ->andReturn('Europe/Madrid');

        ($this->stateAt)('2026-03-10T08:00:00+00:00');

        expect($askedForSchedule)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($askedForZone)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('answers closed with no next opening for a business that published no hours', function () {
        ($this->publishHours)(WeeklyIntervals::none());

        $state = ($this->stateAt)('2026-03-10T08:00:00+00:00');

        expect($state->isOpen())->toBeFalse()
            ->and($state->opensOn)->toBeNull()
            ->and($state->opensAt)->toBeNull();
    });

    it('answers when the business opens next once it has closed for the day', function () {
        ($this->publishHours)(ScheduleFixtures::weeklyIntervals([
            2 => [['09:00', '14:00']],
            3 => [['10:00', '18:00']],
        ]));

        $state = ($this->stateAt)('2026-03-10T20:00:00+00:00');

        expect($state->opensOn)->toBe(Weekday::Wednesday)
            ->and($state->opensAt?->toString())->toBe('10:00');
    });
});
