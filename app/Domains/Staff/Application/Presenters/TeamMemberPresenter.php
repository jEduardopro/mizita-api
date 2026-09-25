<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Presenters;

use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffPhoneBook;
use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Contracts\TeamTemporaryPasswords;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\AccountSnapshot;
use App\Shared\ValueObjects\Paginated;

final class TeamMemberPresenter
{
    public function __construct(
        private readonly AccountDirectory $accounts,
        private readonly StaffProfileRepository $profiles,
        private readonly StaffPhoneBook $phones,
        private readonly StaffProfilePhotos $photos,
        private readonly TeamTemporaryPasswords $temporaryPasswords,
    ) {}

    /**
     * @throws StaffMemberNotFound
     */
    public function describe(StaffMember $member): TeamMemberData
    {
        return $this->describeMany($member->businessId, [$member])[0]
            ?? throw StaffMemberNotFound::withId($member->id);
    }

    /**
     * @param  Paginated<StaffMember>  $page
     * @return Paginated<TeamMemberData>
     */
    public function describePage(string $businessId, Paginated $page): Paginated
    {
        return Paginated::of($this->describeMany($businessId, $page->items), $page->total, $page->pagination);
    }

    /**
     * @param  list<StaffMember>  $members
     * @return list<TeamMemberData>
     */
    public function describeMany(string $businessId, array $members): array
    {
        if ($members === []) {
            return [];
        }

        $accountIds = self::accountIdsOf($members);
        $accounts = $this->accountsOf($accountIds);
        $holdingTemporaryPassword = array_flip($this->temporaryPasswords->availableAmong($accountIds));
        $profiles = $this->profiles->findForStaffMembers($businessId, self::idsOf($members));
        $profileIds = self::profileIdsOf($profiles);
        $phones = $this->phones->forProfiles($profileIds);
        $photoUrls = $this->photos->urlsFor($businessId, $profileIds);

        $described = [];

        foreach ($members as $member) {
            $account = $accounts[$member->accountId] ?? null;

            if ($account === null) {
                continue;
            }

            $profile = $profiles[$member->id] ?? null;

            $described[] = TeamMemberData::fromEntities(
                $member,
                $profile,
                $account,
                $profile === null ? null : ($phones[$profile->id] ?? null),
                $profile === null ? null : ($photoUrls[$profile->id] ?? null),
                isset($holdingTemporaryPassword[$member->accountId]),
            );
        }

        return $described;
    }

    /**
     * @param  list<string>  $accountIds
     * @return array<string, AccountSnapshot>
     */
    private function accountsOf(array $accountIds): array
    {
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
    private static function accountIdsOf(array $members): array
    {
        return array_values(array_unique(array_map(
            static fn (StaffMember $member): string => $member->accountId,
            $members,
        )));
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
