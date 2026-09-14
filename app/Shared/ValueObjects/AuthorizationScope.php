<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

/**
 * Two planes exist conceptually and only one is built. Writing the other down
 * now is what lets the seeder refuse to hand a business role a platform
 * permission before a single platform permission exists.
 *
 * These are exactly the values the scope check constraint on the roles and
 * permissions tables allows: adding a case here means a migration there.
 */
enum AuthorizationScope: string
{
    /** Held by the people who operate the platform. Nothing grants it yet. */
    case Platform = 'platform';

    /** Held inside one business, by its owner or by its staff. */
    case Business = 'business';
}
