<?php

declare(strict_types=1);

use App\Domains\Availability\Application\Services\BusinessBookingHorizon;
use App\Domains\Availability\Contracts\BookingRules;
use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Availability\ValueObjects\SlotRules;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;

beforeEach(function () {
    $this->rules = Mockery::mock(BookingRules::class);
    $this->businessClock = Mockery::mock(BusinessClock::class);

    $this->lastBookableDate = fn (string $now): string => (new BusinessBookingHorizon(
        $this->rules,
        $this->businessClock,
        new FakeClock(new DateTimeImmutable($now)),
    ))->lastBookableDateOf(FakeBusinessContext::BUSINESS_ID);

    $this->publishRules = function (?int $bookingWindowMinutes, string $timezone = 'Europe/Madrid'): void {
        $this->rules->shouldReceive('forBusiness')->andReturn(new SlotRules(0, $bookingWindowMinutes, 15));
        $this->businessClock->shouldReceive('timezoneOf')->andReturn($timezone);
    };
});

describe('the last date a visitor may still book', function () {
    it('reaches the window the business asked for', function () {
        ($this->publishRules)(30 * 24 * 60);

        expect(($this->lastBookableDate)('2026-03-10T08:00:00+00:00'))->toBe('2026-04-09');
    });

    it('reaches a year out when the business set no window at all', function () {
        ($this->publishRules)(null);

        expect(($this->lastBookableDate)('2026-03-10T08:00:00+00:00'))->toBe('2027-03-10');
    });

    it('answers a calendar date, never an instant', function () {
        ($this->publishRules)(1440);

        expect(($this->lastBookableDate)('2026-03-10T08:00:00+00:00'))->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    });

    it('rolls the date over in the local day of the business, not in the day of the server', function () {
        $this->rules->shouldReceive('forBusiness')->andReturn(new SlotRules(0, 1440, 15));
        $this->businessClock->shouldReceive('timezoneOf')->andReturn('Europe/Madrid', 'America/Monterrey');

        expect(($this->lastBookableDate)('2026-03-10T23:30:00+00:00'))->toBe('2026-03-12')
            ->and(($this->lastBookableDate)('2026-03-10T23:30:00+00:00'))->toBe('2026-03-11');
    });

    it('asks both ports about the business it was given', function () {
        $askedForRules = null;
        $askedForZone = null;

        $this->rules->shouldReceive('forBusiness')->once()
            ->with(Mockery::capture($askedForRules))
            ->andReturn(new SlotRules(0, 1440, 15));
        $this->businessClock->shouldReceive('timezoneOf')->once()
            ->with(Mockery::capture($askedForZone))
            ->andReturn('Europe/Madrid');

        ($this->lastBookableDate)('2026-03-10T08:00:00+00:00');

        expect($askedForRules)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($askedForZone)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('keeps the calendar day across a spring-forward boundary', function () {
        ($this->publishRules)(1440);

        expect(($this->lastBookableDate)('2026-03-28T12:00:00+00:00'))->toBe('2026-03-29');
    });

    it('keeps the calendar day across a fall-back boundary', function () {
        ($this->publishRules)(1440);

        expect(($this->lastBookableDate)('2026-10-24T12:00:00+00:00'))->toBe('2026-10-25');
    });
});
