<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Services;

use App\Domains\Staff\Contracts\TeamAllowance;
use App\Domains\Staff\Contracts\TeamOwnership;

final class BookableTeam
{
    public function __construct(
        private readonly TeamAllowance $allowance,
        private readonly TeamOwnership $ownership,
    ) {}

    public function isBookable(string $businessId, string $staffMemberId): bool
    {
        return $this->bookableAmong($businessId, [$staffMemberId]) !== [];
    }

    /**
     * @param  list<string>  $staffMemberIds
     * @return list<string>
     */
    public function bookableAmong(string $businessId, array $staffMemberIds): array
    {
        if ($staffMemberIds === []) {
            return [];
        }

        if ($this->allowance->includesTeam($businessId)) {
            return array_values($staffMemberIds);
        }

        $ownerId = $this->ownership->ownerStaffMemberIdOf($businessId);

        return array_values(array_filter(
            $staffMemberIds,
            static fn (string $staffMemberId): bool => $staffMemberId === $ownerId,
        ));
    }
}
