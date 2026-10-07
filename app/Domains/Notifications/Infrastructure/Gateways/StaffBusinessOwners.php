<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Gateways;

use App\Domains\Notifications\Contracts\BusinessOwners;
use App\Domains\Staff\Contracts\TeamOwnership;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class StaffBusinessOwners implements BusinessOwners
{
    public function __construct(
        private readonly TeamOwnership $ownership,
    ) {}

    public function ownerStaffMemberIdOf(string $businessId): ?string
    {
        try {
            return $this->ownership->ownerStaffMemberIdOf($businessId);
        } catch (ModelNotFoundException) {
            return null;
        }
    }
}
