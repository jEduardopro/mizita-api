<?php

declare(strict_types=1);

namespace App\Domains\Availability\Contracts;

use App\Domains\Availability\Exceptions\StaffMemberNotFound;

interface StaffRoster
{
    /**
     * @throws StaffMemberNotFound
     */
    public function confirmMembership(string $businessId, string $staffMemberId): void;
}
