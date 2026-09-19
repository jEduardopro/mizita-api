<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Availability\ValueObjects\WeeklyIntervals;

final class RuleBasedStaffSchedules implements StaffSchedules
{
    public function __construct(
        private readonly ScheduleRuleRepository $rules,
    ) {}

    public function forBusiness(string $businessId): WeeklyIntervals
    {
        return $this->intervalsFor(ScheduleOwnerType::Business, $businessId);
    }

    public function forStaffMember(string $staffId): WeeklyIntervals
    {
        return $this->intervalsFor(ScheduleOwnerType::StaffMember, $staffId);
    }

    private function intervalsFor(ScheduleOwnerType $ownerType, string $ownerId): WeeklyIntervals
    {
        return WeeklyIntervals::fromRules(
            $this->rules->allForOwner($ownerType, $ownerId),
            $ownerType,
            $ownerId,
        );
    }
}
