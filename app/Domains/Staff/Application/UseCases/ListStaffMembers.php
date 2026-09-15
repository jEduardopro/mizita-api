<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\StaffMemberSummary;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;

final class ListStaffMembers
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly AccountDirectory $accounts,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<list<StaffMemberSummary>>
     */
    public function handle(): UseCaseResponse
    {
        $members = $this->members->allForBusiness($this->business->currentBusinessId());
        $accounts = $this->accountsOf($members);

        $summaries = [];

        foreach ($members as $member) {
            $account = $accounts[$member->accountId] ?? null;

            if ($account === null) {
                continue;
            }

            $summaries[] = StaffMemberSummary::fromEntity($member, $account);
        }

        return UseCaseResponse::success($summaries);
    }

    /**
     * @param  list<StaffMember>  $members
     * @return array<string, AccountSnapshot>
     */
    private function accountsOf(array $members): array
    {
        $accountIds = array_values(array_unique(array_map(
            static fn (StaffMember $member): string => $member->accountId,
            $members,
        )));

        $accounts = [];

        foreach ($this->accounts->describe($accountIds) as $account) {
            $accounts[$account->id] = $account;
        }

        return $accounts;
    }
}
