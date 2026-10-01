<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

enum MembershipRole: string
{
    case Owner = 'owner';

    case Staff = 'staff';
}
