<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\PublicCatalog\Contracts\PublishedTeam;
use App\Domains\PublicCatalog\ValueObjects\PublicTeamMember;
use App\Domains\Staff\Application\Services\BookableTeam;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Services\BookingLinks;

final class StaffPublishedTeam implements PublishedTeam
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly AccountDirectory $accounts,
        private readonly StaffProfileRepository $profiles,
        private readonly StaffProfilePhotos $photos,
        private readonly BookingLinks $bookingLinks,
        private readonly BookableTeam $bookableTeam,
    ) {}

    /**
     * @return list<PublicTeamMember>
     */
    public function forBusiness(string $businessId, string $businessSlug): array
    {
        $members = $this->bookableMembersOf($businessId);

        if ($members === []) {
            return [];
        }

        $names = $this->namesByAccountId($members);
        $profiles = $this->profiles->findForStaffMembers($businessId, self::memberIds($members));
        $photoUrls = $this->photos->urlsFor($businessId, self::profileIds($profiles));
        $team = [];

        foreach ($members as $member) {
            if (! isset($names[$member->accountId])) {
                continue;
            }

            $profile = $profiles[$member->id] ?? null;

            $team[] = new PublicTeamMember(
                id: $member->id,
                name: $names[$member->accountId],
                photoUrl: $profile === null ? null : ($photoUrls[$profile->id] ?? null),
                jobTitle: $profile?->jobTitle()?->value,
                about: $profile?->about()?->value,
                bookingUrl: $this->bookingUrlFor($businessSlug, $profile),
            );
        }

        return $team;
    }

    /**
     * @return list<StaffMember>
     */
    private function bookableMembersOf(string $businessId): array
    {
        $members = $this->members->allForBusiness($businessId);

        if ($members === []) {
            return [];
        }

        $bookableIds = array_flip($this->bookableTeam->bookableAmong($businessId, self::memberIds($members)));

        return array_values(array_filter(
            $members,
            static fn (StaffMember $member): bool => isset($bookableIds[$member->id]),
        ));
    }

    private function bookingUrlFor(string $businessSlug, ?StaffProfile $profile): ?string
    {
        $bookingSlug = $profile?->bookingSlug();

        if ($bookingSlug === null) {
            return null;
        }

        return $this->bookingLinks->forStaffMember($businessSlug, $bookingSlug);
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

    /**
     * @param  list<StaffMember>  $members
     * @return list<string>
     */
    private static function memberIds(array $members): array
    {
        return array_map(static fn (StaffMember $member): string => $member->id, $members);
    }

    /**
     * @param  array<string, StaffProfile>  $profiles
     * @return list<string>
     */
    private static function profileIds(array $profiles): array
    {
        return array_values(array_map(static fn (StaffProfile $profile): string => $profile->id, $profiles));
    }
}
