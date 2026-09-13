<?php

declare(strict_types=1);

namespace App\Domains\Staff\ValueObjects;

/**
 * What a membership grants its holder at one business.
 *
 * A backed enum rather than string constants, so an unknown role cannot reach
 * the database. These values are exactly what the staff_members_role_check
 * constraint allows: adding a case here means adding it there in a migration.
 */
enum StaffRole: string
{
    /** Registered the business. At most one owner membership per account. */
    case Owner = 'owner';

    /** Works at the business and operates its agenda. */
    case Member = 'staff';
}
