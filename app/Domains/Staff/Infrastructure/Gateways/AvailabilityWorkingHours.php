<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;
use App\Domains\Staff\Contracts\WorkingHours;

final class AvailabilityWorkingHours implements WorkingHours
{
    public function __construct(
        private readonly StaffSchedules $schedules,
    ) {}

    /**
     * @param  list<string>  $staffMemberIds
     * @return list<string>
     */
    public function staffWithWorkingHours(string $businessId, array $staffMemberIds): array
    {
        if ($staffMemberIds === []) {
            return [];
        }

        $businessHours = $this->schedules->forBusiness($businessId);

        if (! $businessHours->isEmpty()) {
            return array_values($staffMemberIds);
        }

        return array_values(array_filter(
            $staffMemberIds,
            fn (string $staffMemberId): bool => ! $this->hoursOf($staffMemberId, $businessHours)->isEmpty(),
        ));
    }

    private function hoursOf(string $staffMemberId, WeeklyIntervals $businessHours): WeeklyIntervals
    {
        return $this->schedules->forStaffMember($staffMemberId)->orInheritedFrom($businessHours);
    }
}
