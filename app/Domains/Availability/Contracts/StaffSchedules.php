<?php

declare(strict_types=1);

namespace App\Domains\Availability\Contracts;

use App\Domains\Availability\ValueObjects\WeeklyIntervals;

interface StaffSchedules
{
    public function forBusiness(string $businessId): WeeklyIntervals;

    public function forStaffMember(string $staffId): WeeklyIntervals;
}
