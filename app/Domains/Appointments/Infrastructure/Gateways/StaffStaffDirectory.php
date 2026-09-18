<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\StaffDirectory;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\ValueObjects\StaffMemberSnapshot;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Entities\StaffMember;

final class StaffStaffDirectory implements StaffDirectory
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly AccountDirectory $accounts,
    ) {}

    public function describe(string $businessId, string $staffMemberId): StaffMemberSnapshot
    {
        return $this->describeMany($businessId, [$staffMemberId])[$staffMemberId]
            ?? throw AppointmentStaffNotFound::withId($staffMemberId);
    }

    /**
     * @param  list<string>  $staffMemberIds
     * @return array<string, StaffMemberSnapshot>
     */
    public function describeMany(string $businessId, array $staffMemberIds): array
    {
        $selected = $this->members->findManyIncludingArchived($businessId, $staffMemberIds);

        if ($selected === []) {
            return [];
        }

        $names = $this->namesByAccountId($selected);

        $snapshots = [];

        foreach ($selected as $member) {
            if (isset($names[$member->accountId])) {
                $snapshots[$member->id] = new StaffMemberSnapshot($member->id, $names[$member->accountId]);
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
