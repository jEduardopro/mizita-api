<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Services;

use App\Domains\Availability\Contracts\BookingRules;
use App\Domains\Availability\Contracts\BusinessClock;
use App\Shared\Contracts\Clock;
use DateTimeZone;

final class BusinessBookingHorizon
{
    private const CALENDAR_DATE = 'Y-m-d';

    public function __construct(
        private readonly BookingRules $rules,
        private readonly BusinessClock $businessClock,
        private readonly Clock $clock,
    ) {}

    public function lastBookableDateOf(string $businessId): string
    {
        $zone = new DateTimeZone($this->businessClock->timezoneOf($businessId));

        return $this->rules
            ->forBusiness($businessId)
            ->lastBookableStart($this->clock->now(), $zone)
            ->format(self::CALENDAR_DATE);
    }
}
