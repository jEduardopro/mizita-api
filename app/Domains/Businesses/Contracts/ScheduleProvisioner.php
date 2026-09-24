<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

interface ScheduleProvisioner
{
    public function provisionDefaultsFor(string $businessId, string $ownerStaffMemberId): void;
}
