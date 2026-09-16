<?php

declare(strict_types=1);

namespace App\Domains\Links\ValueObjects;

enum LinkOwnerType: string
{
    case Business = 'business';

    case StaffMember = 'staff_member';
}
