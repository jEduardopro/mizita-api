<?php

declare(strict_types=1);

namespace App\Domains\Addresses\ValueObjects;

enum AddressOwnerType: string
{
    case Business = 'business';

    case StaffMember = 'staff_member';
}
