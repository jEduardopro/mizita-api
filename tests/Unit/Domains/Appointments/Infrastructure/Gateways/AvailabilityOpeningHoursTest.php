<?php

declare(strict_types=1);

use App\Domains\Appointments\Infrastructure\Gateways\AvailabilityOpeningHours;
use App\Domains\Availability\Application\Services\BusinessOpeningHours;
use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\Services\OpeningHoursCalendar;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;

beforeEach(function () {
    $this->schedules = Mockery::mock(StaffSchedules::class);
    $this->businessClock = Mockery::mock(BusinessClock::class);

    $this->publish = function (WeeklyIntervals $hours, string $timezone = 'Europe/Madrid'): void {
        $this->schedules->shouldReceive('forBusiness')->andReturn($hours);
        $this->businessClock->shouldReceive('timezoneOf')->andReturn($timezone);
    };

    $this->isOpenAt = fn (string $now): bool => (new AvailabilityOpeningHours(
        new BusinessOpeningHours(
            $this->schedules,
            $this->businessClock,
            new OpeningHoursCalendar,
            new FakeClock(new DateTimeImmutable($now)),
        ),
    ))->isOpenNow(FakeBusinessContext::BUSINESS_ID);
});

describe('the answer a public booking is gated on', function () {
    it('says the business is open inside its published hours', function () {
        ($this->publish)(ScheduleFixtures::weeklyIntervals([2 => [['09:00', '14:00']]]));

        expect(($this->isOpenAt)('2026-03-10T08:00:00+00:00'))->toBeTrue();
    });

    it('says the business is closed outside its published hours', function () {
        ($this->publish)(ScheduleFixtures::weeklyIntervals([2 => [['09:00', '14:00']]]));

        expect(($this->isOpenAt)('2026-03-10T13:00:00+00:00'))->toBeFalse();
    });

    it('says the business is closed when it publishes no hours at all', function () {
        ($this->publish)(WeeklyIntervals::none());

        expect(($this->isOpenAt)('2026-03-10T08:00:00+00:00'))->toBeFalse();
    });

    it('answers with a plain boolean, so the use case learns nothing about the schedule', function () {
        ($this->publish)(ScheduleFixtures::weeklyIntervals([2 => [['09:00', '14:00']]]));

        expect(($this->isOpenAt)('2026-03-10T08:00:00+00:00'))->toBeBool();
    });

    it('asks the availability domain about the business it was given', function () {
        $asked = null;

        $this->schedules->shouldReceive('forBusiness')->once()
            ->with(Mockery::capture($asked))
            ->andReturn(WeeklyIntervals::none());
        $this->businessClock->shouldReceive('timezoneOf')->once()->andReturn('Europe/Madrid');

        ($this->isOpenAt)('2026-03-10T08:00:00+00:00');

        expect($asked)->toBe(FakeBusinessContext::BUSINESS_ID);
    });
});
