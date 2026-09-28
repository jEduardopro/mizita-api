<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\Exceptions\StaffBookingPageNotFound;

interface PublishedStaffLinks
{
    /**
     * @throws StaffBookingPageNotFound
     */
    public function staffMemberIdFor(string $businessId, string $staffSlug): string;
}
