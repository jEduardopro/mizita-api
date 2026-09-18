<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\ValueObjects\StaffMemberSnapshot;

interface StaffDirectory
{
    /**
     * @throws AppointmentStaffNotFound
     */
    public function describe(string $businessId, string $staffMemberId): StaffMemberSnapshot;

    /**
     * @param  list<string>  $staffMemberIds
     * @return array<string, StaffMemberSnapshot>
     */
    public function describeMany(string $businessId, array $staffMemberIds): array;
}
