<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Contracts;

use App\Domains\Notifications\Exceptions\NotifiedStaffMemberNotFound;
use App\Domains\Notifications\ValueObjects\NotifiedStaffMember;

interface NotifiedStaffMembers
{
    /**
     * @throws NotifiedStaffMemberNotFound
     */
    public function describe(string $businessId, string $staffMemberId): NotifiedStaffMember;
}
