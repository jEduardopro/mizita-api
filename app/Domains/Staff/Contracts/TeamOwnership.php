<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

interface TeamOwnership
{
    public function ownerStaffMemberIdOf(string $businessId): ?string;
}
