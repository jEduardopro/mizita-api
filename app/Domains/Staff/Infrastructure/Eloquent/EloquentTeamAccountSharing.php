<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Eloquent;

use App\Domains\Staff\Contracts\TeamAccountSharing;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\ValueObjects\AccountSharing;

final class EloquentTeamAccountSharing implements TeamAccountSharing
{
    private const USERS_TABLE = 'users';

    private const BUSINESSES_TABLE = 'businesses';

    public function sharingOf(string $accountId, string $businessId): AccountSharing
    {
        if ($this->sharedAmong([$accountId], $businessId) === []) {
            return AccountSharing::ExclusiveToBusiness;
        }

        return AccountSharing::SharedWithOtherBusinesses;
    }

    /**
     * @param  list<string>  $accountIds
     * @return list<string>
     */
    public function sharedAmong(array $accountIds, string $businessId): array
    {
        if ($accountIds === []) {
            return [];
        }

        return StaffMemberModel::query()
            ->join(self::USERS_TABLE, self::USERS_TABLE.'.id', '=', 'staff_members.account_id')
            ->join(self::BUSINESSES_TABLE, self::BUSINESSES_TABLE.'.id', '=', 'staff_members.business_id')
            ->whereIn(self::USERS_TABLE.'.uuid', $accountIds)
            ->where(self::BUSINESSES_TABLE.'.uuid', '<>', $businessId)
            ->distinct()
            ->pluck(self::USERS_TABLE.'.uuid')
            ->map(static fn (mixed $accountId): string => (string) $accountId)
            ->values()
            ->all();
    }
}
