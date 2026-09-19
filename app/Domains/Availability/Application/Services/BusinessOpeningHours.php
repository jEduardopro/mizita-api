<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Services;

use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\Services\OpeningHoursCalendar;
use App\Domains\Availability\ValueObjects\OpenState;
use App\Shared\Contracts\Clock;
use DateTimeZone;

final class BusinessOpeningHours
{
    public function __construct(
        private readonly StaffSchedules $schedules,
        private readonly BusinessClock $businessClock,
        private readonly OpeningHoursCalendar $calendar,
        private readonly Clock $clock,
    ) {}

    public function stateOf(string $businessId): OpenState
    {
        return $this->calendar->stateAt(
            $this->schedules->forBusiness($businessId),
            new DateTimeZone($this->businessClock->timezoneOf($businessId)),
            $this->clock->now(),
        );
    }
}
