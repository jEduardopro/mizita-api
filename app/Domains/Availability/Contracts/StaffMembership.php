<?php

declare(strict_types=1);

namespace App\Domains\Availability\Contracts;

use App\Domains\Availability\Exceptions\StaffMembershipNotFound;

interface StaffMembership
{
    /**
     * @throws StaffMembershipNotFound
     */
    public function staffMemberIdOf(string $businessId, string $accountId): string;
}
