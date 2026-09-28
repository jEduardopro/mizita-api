<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\Exceptions\AppointmentStaffMemberPaused;

interface BookableStaff
{
    /**
     * @throws AppointmentStaffMemberPaused
     */
    public function confirmBookable(string $businessId, string $staffMemberId): void;
}
