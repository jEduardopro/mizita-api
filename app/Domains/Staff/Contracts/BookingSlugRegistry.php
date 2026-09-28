<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Domains\Staff\Exceptions\StaffMemberNotFound;

interface BookingSlugRegistry
{
    /**
     * @return list<string>
     */
    public function slugsMatching(string $businessId, string $base): array;

    public function isHeldByAnother(string $businessId, string $bookingSlug, string $staffMemberId): bool;

    /**
     * @throws StaffMemberNotFound
     */
    public function staffMemberIdFor(string $businessId, string $bookingSlug): string;
}
