<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Contracts;

interface BusinessOwners
{
    public function ownerStaffMemberIdOf(string $businessId): ?string;
}
