<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Services;

use App\Domains\Availability\Application\Dtos\AvailableDayData;
use App\Domains\Availability\Application\Dtos\SlotQuery;
use App\Domains\Availability\Contracts\BookableServices;
use App\Domains\Availability\Contracts\BookableStaff;
use App\Domains\Availability\Contracts\BookedIntervals;
use App\Domains\Availability\Contracts\BookingRules;
use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Availability\Contracts\ExternalBusyIntervals;
use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\Exceptions\AvailabilityRangeTooWide;
use App\Domains\Availability\Exceptions\BookableServiceNotFound;
use App\Domains\Availability\Exceptions\InvalidAvailabilityRange;
use App\Domains\Availability\Exceptions\InvalidBookingBlock;
use App\Domains\Availability\Exceptions\InvalidSlotQuery;
use App\Domains\Availability\Exceptions\StaffMemberNotBookable;
use App\Domains\Availability\Services\SlotCalculator;
use App\Domains\Availability\ValueObjects\AvailableDay;
use App\Domains\Availability\ValueObjects\BookedInterval;
use App\Domains\Availability\ValueObjects\BookingBlock;
use App\Domains\Availability\ValueObjects\LocalDateRange;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use App\Shared\Contracts\Clock;
use DateTimeZone;

final class AvailabilityBoard
{
    public function __construct(
        private readonly BookableServices $services,
        private readonly BookableStaff $staff,
        private readonly StaffSchedules $schedules,
        private readonly BookedIntervals $bookings,
        private readonly ExternalBusyIntervals $externalBusy,
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

        $this->staff->confirmBookable($businessId, $query->staffId);

        $block = $this->blockFor($businessId, $query);
        $requested = $query->range();
        $zone = new DateTimeZone($this->businessClock->timezoneOf($businessId));
        $rules = $this->rules->forBusiness($businessId);
        $now = $this->clock->now();
        $bookable = $requested->clampedTo($now, $rules->lastBookableStart($now, $zone), $zone);

        if ($bookable === null) {
            return $this->describe($requested, []);
        }

        return $this->describe($requested, $this->calculator->slotsBetween(
            $bookable,
            $this->workingHoursOf($businessId, $query->staffId),
            $this->busyWithin($businessId, $query, $bookable, $zone),
            $block,
            $rules,
            $zone,
            $now,
        ));
    }

    /**
     * @param  list<AvailableDay>  $computed
     * @return list<AvailableDayData>
     */
    private function describe(LocalDateRange $requested, array $computed): array
    {
        $computedByDate = [];

        foreach ($computed as $day) {
            $computedByDate[$day->date] = $day;
        }

        return array_map(
            static fn (string $date): AvailableDayData => AvailableDayData::fromDay(
                $computedByDate[$date] ?? AvailableDay::withoutSlots($date),
            ),
            $requested->dates(),
        );
    }

    private function workingHoursOf(string $businessId, string $staffId): WeeklyIntervals
    {
        return $this->schedules
            ->forStaffMember($staffId)
            ->orInheritedFrom($this->schedules->forBusiness($businessId));
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
    private function busyWithin(
        string $businessId,
        SlotQuery $query,
        LocalDateRange $range,
        DateTimeZone $zone,
    ): array {
        return [
            ...$this->bookedWithin($businessId, $query, $range, $zone),
            ...$this->externallyBusyWithin($businessId, $query, $range, $zone),
        ];
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

    /**
     * @return list<BookedInterval>
     */
    private function externallyBusyWithin(
        string $businessId,
        SlotQuery $query,
        LocalDateRange $range,
        DateTimeZone $zone,
    ): array {
        return $this->externalBusy->forStaffBetween(
            $businessId,
            $query->staffId,
            $range->startsAtIn($zone),
            $range->endsAtIn($zone),
        );
    }
}
