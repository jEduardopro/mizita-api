<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

/** These values name the seeded Spatie roles, so they are a storage contract. */
enum StaffRole: string
{
    /** Registered the business. At most one owner membership per account. */
    case Owner = 'owner';

    /** Works at the business and operates its agenda. */
    case Member = 'staff';
}
