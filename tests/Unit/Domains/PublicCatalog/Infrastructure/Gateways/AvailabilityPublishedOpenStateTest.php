<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Services\BusinessOpeningHours;
use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\Services\OpeningHoursCalendar;
use App\Domains\Availability\ValueObjects\OpenState;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AvailabilityPublishedOpenState;
use App\Domains\PublicCatalog\ValueObjects\PublicOpenState;
use Tests\Support\Availability\ScheduleFixtures;
use Tests\Support\FakeClock;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->schedules = Mockery::mock(StaffSchedules::class);
    $this->businessClock = Mockery::mock(BusinessClock::class);

    $this->publish = function (WeeklyIntervals $hours): void {
        $this->schedules->shouldReceive('forBusiness')->andReturn($hours);
        $this->businessClock->shouldReceive('timezoneOf')->andReturn('UTC');
    };

    $this->describeAt = fn (string $now): PublicOpenState => (new AvailabilityPublishedOpenState(
        new BusinessOpeningHours(
            $this->schedules,
            $this->businessClock,
            new OpeningHoursCalendar,
            new FakeClock(new DateTimeImmutable($now)),
        ),
    ))->forBusiness(PublicCatalogFixtures::BUSINESS_ID);
});

describe('translating what the availability domain worked out', function () {
    it('carries the closing time of a business that is open', function () {
        ($this->publish)(ScheduleFixtures::weeklyIntervals([2 => [['09:00', '18:30']]]));

        $state = ($this->describeAt)('2026-03-10T10:00:00+00:00');

        expect($state->isOpen())->toBeTrue()
            ->and($state->closesAt)->toBe('18:30')
            ->and($state->opensOnWeekday)->toBeNull()
            ->and($state->opensAt)->toBeNull();
    });

    it('carries the next opening of a business that is closed', function () {
        ($this->publish)(ScheduleFixtures::weeklyIntervals([3 => [['09:05', '18:00']]]));

        $state = ($this->describeAt)('2026-03-10T10:00:00+00:00');

        expect($state->isOpen())->toBeFalse()
            ->and($state->closesAt)->toBeNull()
            ->and($state->opensOnWeekday)->toBe(3)
            ->and($state->opensAt)->toBe('09:05');
    });

    it('promises no opening at all for a business that publishes no hours', function () {
        ($this->publish)(WeeklyIntervals::none());

        $state = ($this->describeAt)('2026-03-10T10:00:00+00:00');

        expect($state->isOpen())->toBeFalse()
            ->and($state->closesAt)->toBeNull()
            ->and($state->opensOnWeekday)->toBeNull()
            ->and($state->opensAt)->toBeNull();
    });

    it('carries the iso number of every weekday, so Monday is one and Sunday is seven', function (int $weekday) {
        ($this->publish)(ScheduleFixtures::weeklyIntervals([$weekday => [['09:00', '10:00']]]));

        expect(($this->describeAt)('2026-03-09T12:00:00+00:00')->opensOnWeekday)->toBe($weekday);
    })->with([
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6,
        'Sunday' => 7,
    ]);

    it('carries a closing time as a zero padded HH:mm string', function (string $closesAt, string $now) {
        ($this->publish)(ScheduleFixtures::weeklyIntervals([2 => [['00:00', $closesAt]]]));

        expect(($this->describeAt)($now)->closesAt)->toBe($closesAt);
    })->with([
        'one minute past midnight' => ['00:01', '2026-03-10T00:00:00+00:00'],
        'a single digit hour' => ['09:00', '2026-03-10T05:00:00+00:00'],
        'the last minute of the day' => ['23:59', '2026-03-10T05:00:00+00:00'],
    ]);

    it('carries an opening time of midnight as a zero padded string, never as an empty one', function () {
        ($this->publish)(ScheduleFixtures::weeklyIntervals([3 => [['00:00', '01:00']]]));

        expect(($this->describeAt)('2026-03-10T10:00:00+00:00')->opensAt)->toBe('00:00');
    });

    it('asks both ports about the business it was given', function () {
        $askedForSchedule = null;
        $askedForZone = null;

        $this->schedules->shouldReceive('forBusiness')->once()
            ->with(Mockery::capture($askedForSchedule))
            ->andReturn(WeeklyIntervals::none());
        $this->businessClock->shouldReceive('timezoneOf')->once()
            ->with(Mockery::capture($askedForZone))
            ->andReturn('UTC');

        ($this->describeAt)('2026-03-10T10:00:00+00:00');

        expect($askedForSchedule)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($askedForZone)->toBe(PublicCatalogFixtures::BUSINESS_ID);
    });

    it('hands back the public value object, never the one the availability domain owns', function () {
        ($this->publish)(WeeklyIntervals::none());

        expect(($this->describeAt)('2026-03-10T10:00:00+00:00'))
            ->toBeInstanceOf(PublicOpenState::class)
            ->not->toBeInstanceOf(OpenState::class);
    });
});
