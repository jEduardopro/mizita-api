<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\UseCases;

use App\Domains\Staff\Application\Dtos\StaffMemberSummary;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;

final class ListStaffMembers
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly AccountDirectory $accounts,
        private readonly BusinessContext $business,
        private readonly StaffProfileRepository $profiles,
        private readonly StaffProfilePhotos $photos,
    ) {}

    /**
     * @return UseCaseResponse<list<StaffMemberSummary>>
     */
    public function handle(): UseCaseResponse
    {
        $businessId = $this->business->currentBusinessId();
        $members = $this->members->allForBusiness($businessId);

        if ($members === []) {
            return UseCaseResponse::success([]);
        }

        $accounts = $this->accountsOf($members);
        $profiles = $this->profiles->findForStaffMembers($businessId, self::idsOf($members));
        $photoUrls = $this->photos->urlsFor($businessId, self::profileIdsOf($profiles));

        $summaries = [];

        foreach ($members as $member) {
            $account = $accounts[$member->accountId] ?? null;

            if ($account === null) {
                continue;
            }

            $profile = $profiles[$member->id] ?? null;

            $summaries[] = StaffMemberSummary::fromEntity(
                $member,
                $account,
                $profile === null ? null : ($photoUrls[$profile->id] ?? null),
            );
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

    /**
     * @param  list<StaffMember>  $members
     * @return list<string>
     */
    private static function idsOf(array $members): array
    {
        return array_map(static fn (StaffMember $member): string => $member->id, $members);
    }

    /**
     * @param  array<string, StaffProfile>  $profiles
     * @return list<string>
     */
    private static function profileIdsOf(array $profiles): array
    {
        return array_values(array_map(static fn (StaffProfile $profile): string => $profile->id, $profiles));
    }
}
