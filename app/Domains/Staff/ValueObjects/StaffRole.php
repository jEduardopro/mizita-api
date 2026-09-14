<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

enum StaffRole: string
{
    case Owner = 'owner';

    case Member = 'staff';
}
