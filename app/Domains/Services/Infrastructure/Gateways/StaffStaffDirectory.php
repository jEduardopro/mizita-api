<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Gateways;

use App\Domains\Services\Contracts\StaffDirectory;
use App\Domains\Services\ValueObjects\StaffMemberSnapshot;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Entities\StaffMember;

final class StaffStaffDirectory implements StaffDirectory
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly AccountDirectory $accounts,
    ) {}

    /**
     * @param  list<string>  $staffIds
     * @return list<StaffMemberSnapshot>
     */
    public function membersOf(string $businessId, array $staffIds): array
    {
        if ($staffIds === []) {
            return [];
        }

        $selected = array_values(array_filter(
            $this->members->allForBusiness($businessId),
            static fn (StaffMember $member): bool => in_array($member->id, $staffIds, true),
        ));

        if ($selected === []) {
            return [];
        }

        $names = $this->namesByAccountId($selected);

        $snapshots = [];

        foreach ($selected as $member) {
            if (isset($names[$member->accountId])) {
                $snapshots[] = new StaffMemberSnapshot($member->id, $names[$member->accountId]);
            }
        }

        return $snapshots;
    }

    /**
     * @param  list<StaffMember>  $members
     * @return array<string, string>
     */
    private function namesByAccountId(array $members): array
    {
        $accountIds = array_values(array_unique(array_map(
            static fn (StaffMember $member): string => $member->accountId,
            $members,
        )));

        $names = [];

        foreach ($this->accounts->describe($accountIds) as $account) {
            $names[$account->id] = $account->name;
        }

        return $names;
    }
}
