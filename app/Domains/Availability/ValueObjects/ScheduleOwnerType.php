<?php

declare(strict_types=1);

namespace App\Domains\Availability\ValueObjects;

enum ScheduleOwnerType: string
{
    case Business = 'business';

    case StaffMember = 'staff_member';
}
