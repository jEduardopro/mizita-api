<?php

declare(strict_types=1);

namespace App\Domains\Availability\Contracts;

use App\Domains\Availability\Exceptions\StaffMemberNotBookable;

interface BookableStaff
{
    /**
     * @throws StaffMemberNotBookable
     */
    public function confirmBookable(string $businessId, string $staffMemberId): void;
}
