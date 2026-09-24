<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Availability\Application\Dtos\ApplyDefaultHoursInput;
use App\Domains\Availability\Application\UseCases\ApplyDefaultHours;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;
use App\Domains\Businesses\Contracts\ScheduleProvisioner;

final class AvailabilityScheduleProvisioner implements ScheduleProvisioner
{
    public function __construct(
        private readonly ApplyDefaultHours $applyDefaultHours,
    ) {}

    public function provisionDefaultsFor(string $businessId, string $ownerStaffMemberId): void
    {
        $this->applyDefaultHoursTo($businessId, ScheduleOwnerType::Business, $businessId);
        $this->applyDefaultHoursTo($businessId, ScheduleOwnerType::StaffMember, $ownerStaffMemberId);
    }

    private function applyDefaultHoursTo(string $businessId, ScheduleOwnerType $ownerType, string $ownerId): void
    {
        $this->applyDefaultHours->handle(new ApplyDefaultHoursInput(
            businessId: $businessId,
            ownerType: $ownerType,
            ownerId: $ownerId,
        ))->value();
    }
}
