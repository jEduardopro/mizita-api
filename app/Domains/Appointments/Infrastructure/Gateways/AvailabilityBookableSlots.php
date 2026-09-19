<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\BookableSlots;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Availability\Application\Dtos\AvailableDayData;
use App\Domains\Availability\Application\Dtos\SlotQuery;
use App\Domains\Availability\Application\Services\AvailabilityBoard;
use App\Domains\Availability\Exceptions\BookableServiceNotFound;
use App\Domains\Availability\Exceptions\StaffMemberNotBookable;
use DateInterval;
use DateTimeImmutable;

final class AvailabilityBookableSlots implements BookableSlots
{
    private const SURROUNDING_DAY = 'P1D';

    private const CALENDAR_DATE = 'Y-m-d';

    public function __construct(
        private readonly AvailabilityBoard $board,
    ) {}

    public function isBookable(
        string $businessId,
        string $serviceId,
        string $staffMemberId,
        DateTimeImmutable $startsAt,
        ?string $excludingAppointmentId = null,
    ): bool {
        $days = $this->daysAround(
            $businessId,
            $serviceId,
            $staffMemberId,
            $startsAt,
            $excludingAppointmentId,
        );

        foreach ($days as $day) {
            if (self::offers($day, $startsAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<AvailableDayData>
     *
     * @throws AppointmentServiceNotFound
     * @throws AppointmentStaffNotFound
     */
    private function daysAround(
        string $businessId,
        string $serviceId,
        string $staffMemberId,
        DateTimeImmutable $startsAt,
        ?string $excludingAppointmentId,
    ): array {
        $surroundingDay = new DateInterval(self::SURROUNDING_DAY);

        try {
            return $this->board->forBusiness($businessId, new SlotQuery(
                serviceId: $serviceId,
                staffId: $staffMemberId,
                from: $startsAt->sub($surroundingDay)->format(self::CALENDAR_DATE),
                to: $startsAt->add($surroundingDay)->format(self::CALENDAR_DATE),
                excludingAppointmentId: $excludingAppointmentId,
            ));
        } catch (BookableServiceNotFound $missing) {
            throw AppointmentServiceNotFound::withId($serviceId, $missing);
        } catch (StaffMemberNotBookable $unavailable) {
            throw AppointmentStaffNotFound::withId($staffMemberId, $unavailable);
        }
    }

    private static function offers(AvailableDayData $day, DateTimeImmutable $startsAt): bool
    {
        foreach ($day->starts as $start) {
            if ($start->getTimestamp() === $startsAt->getTimestamp()) {
                return true;
            }
        }

        return false;
    }
}
