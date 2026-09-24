<?php

declare(strict_types=1);

namespace App\Domains\Phones\ValueObjects;

enum PhoneOwnerType: string
{
    case Business = 'business';

    case StaffMember = 'staff_member';

    case Customer = 'customer';

    case StaffProfile = 'staff_profile';
}
