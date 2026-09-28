<?php

declare(strict_types=1);

namespace App\Domains\Availability\Infrastructure\Gateways;

use App\Domains\Availability\Contracts\BookableStaff;
use App\Domains\Availability\Exceptions\StaffMemberNotBookable;
use App\Domains\Staff\Application\Services\BookableTeam;

final class StaffBookableStaff implements BookableStaff
{
    public function __construct(
        private readonly BookableTeam $team,
    ) {}

    public function confirmBookable(string $businessId, string $staffMemberId): void
    {
        if ($this->team->isBookable($businessId, $staffMemberId)) {
            return;
        }

        throw StaffMemberNotBookable::underCurrentPlan($staffMemberId);
    }
}
