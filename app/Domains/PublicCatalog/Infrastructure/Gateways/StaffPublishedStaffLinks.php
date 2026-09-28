<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Gateways;

use App\Domains\PublicCatalog\Contracts\PublishedStaffLinks;
use App\Domains\PublicCatalog\Exceptions\StaffBookingPageNotFound;
use App\Domains\Staff\Contracts\BookingSlugRegistry;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;

final class StaffPublishedStaffLinks implements PublishedStaffLinks
{
    public function __construct(
        private readonly BookingSlugRegistry $bookingSlugs,
    ) {}

    public function staffMemberIdFor(string $businessId, string $staffSlug): string
    {
        try {
            return $this->bookingSlugs->staffMemberIdFor($businessId, $staffSlug);
        } catch (StaffMemberNotFound $absent) {
            throw StaffBookingPageNotFound::withSlug($staffSlug, $absent);
        }
    }
}
