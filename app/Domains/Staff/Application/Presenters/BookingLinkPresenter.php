<?php

declare(strict_types=1);

namespace App\Domains\Staff\Application\Presenters;

use App\Domains\Staff\Application\Dtos\BookingLinkData;
use App\Domains\Staff\Application\Dtos\BookingLinkStatus;
use App\Domains\Staff\Application\Services\BookingReadinessAssessor;
use App\Domains\Staff\Contracts\BusinessSlugs;
use App\Domains\Staff\Entities\StaffProfile;
use App\Domains\Staff\Exceptions\BookingLinkNotFound;
use App\Domains\Staff\Services\BookingLinks;
use App\Domains\Staff\ValueObjects\BookingSlug;

final class BookingLinkPresenter
{
    public function __construct(
        private readonly BookingReadinessAssessor $readiness,
        private readonly BusinessSlugs $businesses,
        private readonly BookingLinks $links,
    ) {}

    /**
     * @throws BookingLinkNotFound
     */
    public function linkOf(StaffProfile $profile): BookingLinkData
    {
        $bookingSlug = $profile->bookingSlug()
            ?? throw BookingLinkNotFound::forStaffMember($profile->staffMemberId);

        return $this->linkFor($this->businesses->slugOf($profile->businessId), $bookingSlug);
    }

    public function statusOf(StaffProfile $profile): BookingLinkStatus
    {
        return $this->statusesOf(
            $profile->businessId,
            [$profile->staffMemberId],
            [$profile->staffMemberId => $profile],
        )[$profile->staffMemberId];
    }

    /**
     * @param  list<string>  $staffMemberIds
     * @param  array<string, StaffProfile>  $profiles
     * @return array<string, BookingLinkStatus>
     */
    public function statusesOf(string $businessId, array $staffMemberIds, array $profiles): array
    {
        $readiness = $this->readiness->assessMany($businessId, $staffMemberIds);
        $businessSlug = $this->businessSlugWhenAnyIsLinked($businessId, $profiles);

        $statuses = [];

        foreach ($staffMemberIds as $staffMemberId) {
            $bookingSlug = ($profiles[$staffMemberId] ?? null)?->bookingSlug();

            $statuses[$staffMemberId] = new BookingLinkStatus(
                link: $bookingSlug === null || $businessSlug === null ? null : $this->linkFor($businessSlug, $bookingSlug),
                blockers: $readiness[$staffMemberId]->blockers,
            );
        }

        return $statuses;
    }

    /**
     * @param  array<string, StaffProfile>  $profiles
     */
    private function businessSlugWhenAnyIsLinked(string $businessId, array $profiles): ?string
    {
        foreach ($profiles as $profile) {
            if ($profile->bookingSlug() !== null) {
                return $this->businesses->slugOf($businessId);
            }
        }

        return null;
    }

    private function linkFor(string $businessSlug, BookingSlug $bookingSlug): BookingLinkData
    {
        return new BookingLinkData(
            slug: $bookingSlug->value,
            url: $this->links->forStaffMember($businessSlug, $bookingSlug),
        );
    }
}
