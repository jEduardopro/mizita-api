<?php

declare(strict_types=1);

namespace App\Domains\Staff\Events;

use App\Domains\Staff\ValueObjects\StaffRole;

/**
 * Somebody was given a membership of a business.
 *
 * Domain event: a plain readonly payload carrying identifiers, not entities.
 * The role travels with it because a listener that reacts differently to an
 * owner should not have to query the row back to find out.
 */
final readonly class StaffMemberRegistered
{
    public function __construct(
        public string $id,
        public string $businessId,
        public StaffRole $role,
    ) {}
}
