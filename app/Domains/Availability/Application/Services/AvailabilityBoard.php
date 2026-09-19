<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Services;

use App\Domains\Availability\Application\Dtos\AvailableDayData;
use App\Domains\Availability\Application\Dtos\SlotQuery;
use App\Domains\Availability\Contracts\BookableServices;
use App\Domains\Availability\Contracts\BookedIntervals;
use App\Domains\Availability\Contracts\BookingRules;
use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\Exceptions\AvailabilityRangeTooWide;
use App\Domains\Availability\Exceptions\BookableServiceNotFound;
use App\Domains\Availability\Exceptions\InvalidAvailabilityRange;
use App\Domains\Availability\Exceptions\InvalidBookingBlock;
use App\Domains\Availability\Exceptions\InvalidSlotQuery;
use App\Domains\Availability\Exceptions\StaffMemberNotBookable;
use App\Domains\Availability\Services\SlotCalculator;
use App\Domains\Availability\ValueObjects\BookedInterval;
use App\Domains\Availability\ValueObjects\BookingBlock;
use App\Domains\Availability\ValueObjects\LocalDateRange;
use App\Shared\Contracts\Clock;
use DateTimeZone;

final class AvailabilityBoard
{
    public function __construct(
        private readonly BookableServices $services,
        private readonly StaffSchedules $schedules,
        private readonly BookedIntervals $bookings,
        private readonly BookingRules $rules,
        private readonly BusinessClock $businessClock,
        private readonly SlotCalculator $calculator,
        private readonly Clock $clock,
    ) {}

    /**
     * @return list<AvailableDayData>
     *
     * @throws InvalidSlotQuery
     * @throws InvalidAvailabilityRange
     * @throws AvailabilityRangeTooWide
     * @throws BookableServiceNotFound
     * @throws StaffMemberNotBookable
     * @throws InvalidBookingBlock
     */
    public function forBusiness(string $businessId, SlotQuery $query): array
    {
        $query->validate();

        $range = $query->range();
        $zone = new DateTimeZone($this->businessClock->timezoneOf($businessId));
        $businessHours = $this->schedules->forBusiness($businessId);
        $staffHours = $this->schedules->forStaffMember($query->staffId)->orInheritedFrom($businessHours);

        $days = $this->calculator->slotsBetween(
            $range,
            $businessHours,
            $staffHours,
            $this->bookedWithin($businessId, $query, $range, $zone),
            $this->blockFor($businessId, $query),
            $this->rules->forBusiness($businessId),
            $zone,
            $this->clock->now(),
        );

        return array_map(AvailableDayData::fromDay(...), $days);
    }

    /**
     * @throws BookableServiceNotFound
     * @throws StaffMemberNotBookable
     * @throws InvalidBookingBlock
     */
    private function blockFor(string $businessId, SlotQuery $query): BookingBlock
    {
        return $this->services
            ->describe($businessId, $query->serviceId)
            ->blockFor($query->staffId);
    }

    /**
     * @return list<BookedInterval>
     */
    private function bookedWithin(
        string $businessId,
        SlotQuery $query,
        LocalDateRange $range,
        DateTimeZone $zone,
    ): array {
        return $this->bookings->forStaffBetween(
            $businessId,
            $query->staffId,
            $range->startsAtIn($zone),
            $range->endsAtIn($zone),
            $query->excludingAppointmentId,
        );
    }
}
