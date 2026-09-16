<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\PublicCatalog\Contracts\PublishedTeam;
use App\Domains\PublicCatalog\ValueObjects\PublicTeamMember;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Entities\StaffMember;

final class StaffPublishedTeam implements PublishedTeam
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly AccountDirectory $accounts,
    ) {}

    /**
     * @return list<PublicTeamMember>
     */
    public function forBusiness(string $businessId): array
    {
        $members = $this->members->allForBusiness($businessId);

        if ($members === []) {
            return [];
        }

        $names = $this->namesByAccountId($members);
        $team = [];

        foreach ($members as $member) {
            if (isset($names[$member->accountId])) {
                $team[] = new PublicTeamMember($member->id, $names[$member->accountId]);
            }
        }

        return $team;
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
